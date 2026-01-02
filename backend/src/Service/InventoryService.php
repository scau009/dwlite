<?php

namespace App\Service;

use App\Entity\InboundOrder;
use App\Entity\InventoryTransaction;
use App\Entity\Merchant;
use App\Entity\MerchantInventory;
use App\Entity\ProductSku;
use App\Entity\Warehouse;
use App\Repository\MerchantInventoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class InventoryService
{
    public function __construct(
        private MerchantInventoryRepository $inventoryRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 获取或创建库存记录.
     */
    public function getOrCreateInventory(
        Merchant $merchant,
        Warehouse $warehouse,
        ProductSku $sku
    ): MerchantInventory {
        $inventory = $this->inventoryRepository->findOneBy([
            'merchant' => $merchant,
            'warehouse' => $warehouse,
            'productSku' => $sku,
        ]);

        if ($inventory === null) {
            $inventory = new MerchantInventory();
            $inventory->setMerchant($merchant);
            $inventory->setWarehouse($warehouse);
            $inventory->setProductSku($sku);

            $this->entityManager->persist($inventory);
            $this->logger->info('Created new inventory record', [
                'merchant_id' => $merchant->getId(),
                'warehouse_id' => $warehouse->getId(),
                'sku_id' => $sku->getId(),
            ]);
        }

        return $inventory;
    }

    /**
     * 发货时增加在途库存.
     */
    public function addInTransitStock(
        InboundOrder $order,
        ?string $operatorId = null,
        ?string $operatorName = null
    ): void {
        foreach ($order->getItems() as $item) {
            $inventory = $this->getOrCreateInventory(
                $order->getMerchant(),
                $order->getWarehouse(),
                $item->getProductSku()
            );

            $balanceBefore = $inventory->getQuantityInTransit();
            $inventory->addInTransit($item->getExpectedQuantity());
            $balanceAfter = $inventory->getQuantityInTransit();

            // 更新平均成本
            if ($item->getUnitCost() !== null) {
                $inventory->updateAverageCost(
                    $item->getExpectedQuantity(),
                    $item->getUnitCost()
                );
            }

            // 记录流水
            $this->recordTransaction(
                $inventory,
                InventoryTransaction::TYPE_INBOUND_TRANSIT,
                'in_transit',
                $item->getExpectedQuantity(),
                $balanceBefore,
                $balanceAfter,
                InventoryTransaction::REF_INBOUND_ORDER,
                $order->getId(),
                $order->getOrderNo(),
                $item->getUnitCost(),
                $operatorId,
                $operatorName,
                sprintf('入库单 %s 发货，SKU: %s', $order->getOrderNo(), $item->getStyleNumber().'-'.$item->getSkuName())
            );

            $this->entityManager->flush();
        }

        $this->logger->info('Added in-transit stock for inbound order', [
            'order_id' => $order->getId(),
            'order_no' => $order->getOrderNo(),
        ]);
    }

    /**
     * 确认收货，在途转可用.
     */
    public function confirmInbound(
        InboundOrder $order,
        ?string $operatorId = null,
        ?string $operatorName = null
    ): void {
        foreach ($order->getItems() as $item) {
            $inventory = $this->getOrCreateInventory(
                $order->getMerchant(),
                $order->getWarehouse(),
                $item->getProductSku()
            );

            $receivedQty = $item->getReceivedQuantity();
            $damagedQty = $item->getDamagedQuantity();

            // 在途转可用/损坏
            $inventory->confirmInbound($receivedQty, $damagedQty);

            // 记录可用库存增加流水
            if ($receivedQty > 0) {
                $this->recordTransaction(
                    $inventory,
                    InventoryTransaction::TYPE_INBOUND_STOCK,
                    'available',
                    $receivedQty,
                    $inventory->getQuantityAvailable() - $receivedQty,
                    $inventory->getQuantityAvailable(),
                    InventoryTransaction::REF_INBOUND_ORDER,
                    $order->getId(),
                    $order->getOrderNo(),
                    $item->getUnitCost(),
                    $operatorId,
                    $operatorName,
                    sprintf('入库单 %s 收货上架', $order->getOrderNo())
                );
            }

            // 记录损坏库存流水
            if ($damagedQty > 0) {
                $this->recordTransaction(
                    $inventory,
                    InventoryTransaction::TYPE_INBOUND_DAMAGED,
                    'damaged',
                    $damagedQty,
                    $inventory->getQuantityDamaged() - $damagedQty,
                    $inventory->getQuantityDamaged(),
                    InventoryTransaction::REF_INBOUND_ORDER,
                    $order->getId(),
                    $order->getOrderNo(),
                    null,
                    $operatorId,
                    $operatorName,
                    sprintf('入库单 %s 收货发现损坏', $order->getOrderNo())
                );
            }

            $this->entityManager->flush();
        }

        $this->logger->info('Confirmed inbound stock for order', [
            'order_id' => $order->getId(),
            'order_no' => $order->getOrderNo(),
        ]);
    }

    /**
     * 清除剩余的在途库存（入库单完结时调用）
     * 当预期数量 > 实收数量时，差异部分的在途库存需要清除.
     */
    public function clearRemainingInTransit(
        InboundOrder $order,
        ?string $operatorId = null,
        ?string $operatorName = null
    ): void {
        foreach ($order->getItems() as $item) {
            // 计算差异：预期 - 实收 - 损坏
            $expectedQty = $item->getExpectedQuantity();
            $receivedQty = $item->getReceivedQuantity();
            $damagedQty = $item->getDamagedQuantity();
            $difference = $expectedQty - $receivedQty - $damagedQty;

            if ($difference <= 0) {
                continue; // 没有差异或超收，跳过
            }

            $inventory = $this->getOrCreateInventory(
                $order->getMerchant(),
                $order->getWarehouse(),
                $item->getProductSku()
            );

            $balanceBefore = $inventory->getQuantityInTransit();

            // 扣减在途库存
            $inventory->reduceInTransit($difference);

            $balanceAfter = $inventory->getQuantityInTransit();

            // 记录流水
            $this->recordTransaction(
                $inventory,
                InventoryTransaction::TYPE_INBOUND_SHORTAGE,
                'in_transit',
                -$difference,
                $balanceBefore,
                $balanceAfter,
                InventoryTransaction::REF_INBOUND_ORDER,
                $order->getId(),
                $order->getOrderNo(),
                null,
                $operatorId,
                $operatorName,
                sprintf('入库单 %s 完结，清除差异在途库存', $order->getOrderNo())
            );
        }

        $this->entityManager->flush();

        $this->logger->info('Cleared remaining in-transit stock for order', [
            'order_id' => $order->getId(),
            'order_no' => $order->getOrderNo(),
        ]);
    }

    /**
     * 锁定库存（订单占用）.
     */
    public function reserveStock(
        MerchantInventory $inventory,
        int $quantity,
        string $referenceType,
        string $referenceId,
        ?string $referenceNo = null,
        ?string $operatorId = null,
        ?string $operatorName = null
    ): void {
        $balanceBefore = $inventory->getQuantityAvailable();
        $inventory->reserve($quantity);
        $balanceAfter = $inventory->getQuantityAvailable();

        $this->recordTransaction(
            $inventory,
            InventoryTransaction::TYPE_OUTBOUND_RESERVE,
            'available',
            -$quantity,
            $balanceBefore,
            $balanceAfter,
            $referenceType,
            $referenceId,
            $referenceNo,
            null,
            $operatorId,
            $operatorName,
            '订单锁定库存'
        );

        // 同时记录锁定库存增加
        $reservedBefore = $inventory->getQuantityReserved() - $quantity;
        $this->recordTransaction(
            $inventory,
            InventoryTransaction::TYPE_OUTBOUND_RESERVE,
            'reserved',
            $quantity,
            $reservedBefore,
            $inventory->getQuantityReserved(),
            $referenceType,
            $referenceId,
            $referenceNo,
            null,
            $operatorId,
            $operatorName,
            '订单锁定库存'
        );

        $this->entityManager->flush();
    }

    /**
     * 锁定破损库存（订单占用）.
     */
    public function reserveDamagedStock(
        MerchantInventory $inventory,
        int $quantity,
        string $referenceType,
        string $referenceId,
        ?string $referenceNo = null,
        ?string $operatorId = null,
        ?string $operatorName = null
    ): void {
        $balanceBefore = $inventory->getQuantityDamaged();
        $inventory->reserveDamaged($quantity);
        $balanceAfter = $inventory->getQuantityDamaged();

        $this->recordTransaction(
            $inventory,
            InventoryTransaction::TYPE_OUTBOUND_RESERVE,
            'damaged',
            -$quantity,
            $balanceBefore,
            $balanceAfter,
            $referenceType,
            $referenceId,
            $referenceNo,
            null,
            $operatorId,
            $operatorName,
            '订单锁定破损库存'
        );

        // 同时记录锁定库存增加
        $reservedBefore = $inventory->getQuantityReserved() - $quantity;
        $this->recordTransaction(
            $inventory,
            InventoryTransaction::TYPE_OUTBOUND_RESERVE,
            'reserved',
            $quantity,
            $reservedBefore,
            $inventory->getQuantityReserved(),
            $referenceType,
            $referenceId,
            $referenceNo,
            null,
            $operatorId,
            $operatorName,
            '订单锁定破损库存'
        );

        $this->entityManager->flush();
    }

    /**
     * 释放库存（订单取消）.
     */
    public function releaseStock(
        MerchantInventory $inventory,
        int $quantity,
        string $referenceType,
        string $referenceId,
        ?string $referenceNo = null,
        ?string $operatorId = null,
        ?string $operatorName = null
    ): void {
        $balanceBefore = $inventory->getQuantityReserved();
        $inventory->release($quantity);
        $balanceAfter = $inventory->getQuantityReserved();

        $this->recordTransaction(
            $inventory,
            InventoryTransaction::TYPE_OUTBOUND_RELEASE,
            'reserved',
            -$quantity,
            $balanceBefore,
            $balanceAfter,
            $referenceType,
            $referenceId,
            $referenceNo,
            null,
            $operatorId,
            $operatorName,
            '订单取消释放库存'
        );

        // 同时记录可用库存增加
        $availableBefore = $inventory->getQuantityAvailable() - $quantity;
        $this->recordTransaction(
            $inventory,
            InventoryTransaction::TYPE_OUTBOUND_RELEASE,
            'available',
            $quantity,
            $availableBefore,
            $inventory->getQuantityAvailable(),
            $referenceType,
            $referenceId,
            $referenceNo,
            null,
            $operatorId,
            $operatorName,
            '订单取消释放库存'
        );

        $this->entityManager->flush();
    }

    /**
     * 确认出库（发货扣减）.
     */
    public function confirmOutbound(
        MerchantInventory $inventory,
        int $quantity,
        string $referenceType,
        string $referenceId,
        ?string $referenceNo = null,
        ?string $operatorId = null,
        ?string $operatorName = null
    ): void {
        $balanceBefore = $inventory->getQuantityReserved();
        $inventory->confirmOutbound($quantity);
        $balanceAfter = $inventory->getQuantityReserved();

        $this->recordTransaction(
            $inventory,
            InventoryTransaction::TYPE_OUTBOUND_SHIP,
            'reserved',
            -$quantity,
            $balanceBefore,
            $balanceAfter,
            $referenceType,
            $referenceId,
            $referenceNo,
            null,
            $operatorId,
            $operatorName,
            '订单发货扣减库存'
        );

        $this->entityManager->flush();
    }

    /**
     * 记录库存流水.
     */
    private function recordTransaction(
        MerchantInventory $inventory,
        string $type,
        string $stockType,
        int $quantity,
        int $balanceBefore,
        int $balanceAfter,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $referenceNo = null,
        ?string $unitCost = null,
        ?string $operatorId = null,
        ?string $operatorName = null,
        ?string $notes = null
    ): void {
        $transaction = new InventoryTransaction();
        $transaction->setMerchantInventory($inventory);
        $transaction->setType($type);
        $transaction->setStockType($stockType);
        $transaction->setQuantity($quantity);
        $transaction->setBalanceBefore($balanceBefore);
        $transaction->setBalanceAfter($balanceAfter);

        if ($referenceType !== null) {
            $transaction->setReferenceType($referenceType);
        }
        if ($referenceId !== null) {
            $transaction->setReferenceId($referenceId);
        }
        if ($referenceNo !== null) {
            $transaction->setReferenceNo($referenceNo);
        }
        if ($unitCost !== null) {
            $transaction->setUnitCost($unitCost);
        }
        if ($operatorId !== null) {
            $transaction->setOperatorId($operatorId);
        }
        if ($operatorName !== null) {
            $transaction->setOperatorName($operatorName);
        }
        if ($notes !== null) {
            $transaction->setNotes($notes);
        }

        $this->entityManager->persist($transaction);
    }

    /**
     * 回滚在途库存（取消发货时）.
     */
    public function rollbackInTransit(
        InboundOrder $order,
        ?string $operatorId = null,
        ?string $operatorName = null
    ): void {
        foreach ($order->getItems() as $item) {
            $inventory = $this->inventoryRepository->findOneBy([
                'merchant' => $order->getMerchant(),
                'warehouse' => $order->getWarehouse(),
                'productSku' => $item->getProductSku(),
            ]);

            if ($inventory === null) {
                continue;
            }

            $balanceBefore = $inventory->getQuantityInTransit();
            $quantityToRemove = min($item->getExpectedQuantity(), $balanceBefore);
            $inventory->setQuantityInTransit($balanceBefore - $quantityToRemove);
            $balanceAfter = $inventory->getQuantityInTransit();

            // 记录流水
            $this->recordTransaction(
                $inventory,
                InventoryTransaction::TYPE_ADJUSTMENT_SUB,
                'in_transit',
                -$quantityToRemove,
                $balanceBefore,
                $balanceAfter,
                InventoryTransaction::REF_INBOUND_ORDER,
                $order->getId(),
                $order->getOrderNo(),
                null,
                $operatorId,
                $operatorName,
                sprintf('入库单 %s 取消，回滚在途库存', $order->getOrderNo())
            );

            $this->entityManager->flush();
        }

        $this->logger->info('Rolled back in-transit stock for cancelled order', [
            'order_id' => $order->getId(),
            'order_no' => $order->getOrderNo(),
        ]);
    }
}
