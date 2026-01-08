<?php

namespace App\Service;

use App\Dto\Merchant\ImportInventoryConfirmItemRequest;
use App\Entity\InboundOrder;
use App\Entity\InventoryTransaction;
use App\Entity\Merchant;
use App\Entity\MerchantInventory;
use App\Entity\ProductSku;
use App\Entity\Warehouse;
use App\Repository\MerchantInventoryRepository;
use App\Repository\ProductSkuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class InventoryService
{
    public function __construct(
        private MerchantInventoryRepository $inventoryRepository,
        private ProductSkuRepository $skuRepository,
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
            $inventory->setCurrency($sku->getCurrency());

            $this->entityManager->persist($inventory);
            $this->logger->info('Created new inventory record', [
                'merchant_id' => $merchant->getId(),
                'warehouse_id' => $warehouse->getId(),
                'sku_id' => $sku->getId(),
                'currency' => $inventory->getCurrency(),
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

            // 更新平均成本（必须在 confirmInbound 之前调用，因为 updateAverageCost 使用当前库存数量计算加权平均）
            if ($item->getUnitCost() !== null && $receivedQty > 0) {
                $inventory->updateAverageCost($receivedQty, $item->getUnitCost());
            }

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

    /**
     * 初始化库存（逻辑仓库）.
     *
     * @throws \InvalidArgumentException 如果仓库不是商户逻辑仓库或库存已存在
     */
    public function initializeInventory(
        Merchant $merchant,
        Warehouse $warehouse,
        ProductSku $sku,
        int $quantity,
        ?string $unitCost = null,
        ?string $costCurrency = 'CNY',
        ?string $notes = null,
        ?string $operatorId = null,
        ?string $operatorName = null
    ): MerchantInventory {
        // 验证仓库是逻辑仓库
        if (!$warehouse->isMerchantWarehouse()) {
            throw new \InvalidArgumentException('Only merchant warehouses can use initializeInventory');
        }

        // 验证仓库属于该商户
        if ($warehouse->getMerchant()?->getId() !== $merchant->getId()) {
            throw new \InvalidArgumentException('Warehouse does not belong to merchant');
        }

        // 检查库存是否已存在
        $existingInventory = $this->inventoryRepository->findOneBy([
            'merchant' => $merchant,
            'warehouse' => $warehouse,
            'productSku' => $sku,
        ]);

        if ($existingInventory !== null) {
            throw new \InvalidArgumentException('Inventory already exists for this SKU in this warehouse');
        }

        // 创建库存记录
        $inventory = new MerchantInventory();
        $inventory->setMerchant($merchant);
        $inventory->setWarehouse($warehouse);
        $inventory->setProductSku($sku);
        $inventory->setCurrency($costCurrency ?? 'CNY');
        $inventory->setQuantityAvailable($quantity);

        if ($unitCost !== null) {
            $inventory->setAverageCost($unitCost);
        }

        $this->entityManager->persist($inventory);

        // 记录流水
        $transaction = InventoryTransaction::createInit(
            $inventory,
            $quantity,
            $unitCost,
            $notes,
            $operatorId,
            $operatorName
        );
        $this->entityManager->persist($transaction);

        $this->entityManager->flush();

        $this->logger->info('Initialized inventory for merchant warehouse', [
            'inventory_id' => $inventory->getId(),
            'merchant_id' => $merchant->getId(),
            'warehouse_id' => $warehouse->getId(),
            'sku_id' => $sku->getId(),
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
        ]);

        return $inventory;
    }

    /**
     * 调整库存（逻辑仓库）.
     *
     * @param string $adjustmentType 调整类型：set（设为指定值）、increase（增加）、decrease（减少）
     *
     * @throws \InvalidArgumentException 如果仓库不是商户逻辑仓库或调整无效
     */
    public function adjustInventory(
        MerchantInventory $inventory,
        string $adjustmentType,
        int $quantity,
        ?string $unitCost = null,
        ?string $notes = null,
        ?string $operatorId = null,
        ?string $operatorName = null
    ): MerchantInventory {
        // 验证是逻辑仓库
        if (!$inventory->isMerchantOwned()) {
            throw new \InvalidArgumentException('adjustInventory can only be used for merchant-owned inventory');
        }

        $balanceBefore = $inventory->getQuantityAvailable();
        $quantityChange = 0;
        $balanceAfter = 0;

        switch ($adjustmentType) {
            case 'set':
                $balanceAfter = max(0, $quantity);
                $quantityChange = $balanceAfter - $balanceBefore;
                break;

            case 'increase':
                if ($quantity < 0) {
                    throw new \InvalidArgumentException('Increase quantity must be positive');
                }
                $quantityChange = $quantity;
                $balanceAfter = $balanceBefore + $quantity;
                break;

            case 'decrease':
                if ($quantity < 0) {
                    throw new \InvalidArgumentException('Decrease quantity must be positive');
                }
                if ($quantity > $balanceBefore) {
                    throw new \InvalidArgumentException('Cannot decrease more than available');
                }
                $quantityChange = -$quantity;
                $balanceAfter = $balanceBefore - $quantity;
                break;

            default:
                throw new \InvalidArgumentException('Invalid adjustment type');
        }

        // 更新库存
        $inventory->setQuantityAvailable($balanceAfter);

        // 更新成本
        if ($unitCost !== null) {
            $inventory->setAverageCost($unitCost);
        }

        // 记录流水
        $transaction = InventoryTransaction::createAdjust(
            $inventory,
            $quantityChange,
            $balanceBefore,
            $balanceAfter,
            $unitCost,
            $notes,
            $operatorId,
            $operatorName
        );
        $this->entityManager->persist($transaction);

        $this->entityManager->flush();

        $this->logger->info('Adjusted inventory for merchant warehouse', [
            'inventory_id' => $inventory->getId(),
            'adjustment_type' => $adjustmentType,
            'quantity_change' => $quantityChange,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'unit_cost' => $unitCost,
        ]);

        return $inventory;
    }

    /**
     * 批量导入库存（逻辑仓库）.
     *
     * @param ImportInventoryConfirmItemRequest[] $items
     * @param string                              $conflictStrategy skip（跳过已存在）、override（覆盖）、add（累加）
     *
     * @return array{imported: int, skipped: int, errors: array<string, string>}
     *
     * @throws \InvalidArgumentException 如果仓库不是商户逻辑仓库
     */
    public function batchImportInventory(
        Merchant $merchant,
        Warehouse $warehouse,
        array $items,
        string $conflictStrategy = 'skip',
        ?string $operatorId = null,
        ?string $operatorName = null
    ): array {
        // 验证仓库是逻辑仓库
        if (!$warehouse->isMerchantWarehouse()) {
            throw new \InvalidArgumentException('Only merchant warehouses can use batchImportInventory');
        }

        // 验证仓库属于该商户
        if ($warehouse->getMerchant()?->getId() !== $merchant->getId()) {
            throw new \InvalidArgumentException('Warehouse does not belong to merchant');
        }

        $result = [
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($items as $item) {
            $skuCode = $item->skuCode;

            // 获取 SKU
            $sku = $this->skuRepository->find($item->productSkuId);
            if (!$sku) {
                $result['errors'][$skuCode] = 'SKU not found';
                continue;
            }

            // 检查是否已存在库存
            $existingInventory = $this->inventoryRepository->findOneBy([
                'merchant' => $merchant,
                'warehouse' => $warehouse,
                'productSku' => $sku,
            ]);

            if ($existingInventory !== null) {
                switch ($conflictStrategy) {
                    case 'skip':
                        ++$result['skipped'];
                        continue 2; // Continue to next item
                    case 'override':
                        // 覆盖：直接设置新数量
                        $this->updateInventoryFromImport(
                            $existingInventory,
                            $item->quantity,
                            $item->unitCost,
                            $item->costCurrency,
                            $operatorId,
                            $operatorName,
                            true
                        );
                        ++$result['imported'];
                        continue 2;
                    case 'add':
                        // 累加：在现有基础上增加
                        $newQuantity = $existingInventory->getQuantityAvailable() + $item->quantity;
                        $this->updateInventoryFromImport(
                            $existingInventory,
                            $newQuantity,
                            $item->unitCost,
                            $item->costCurrency,
                            $operatorId,
                            $operatorName,
                            false
                        );
                        ++$result['imported'];
                        continue 2;
                }
            }

            // 创建新库存记录
            $inventory = new MerchantInventory();
            $inventory->setMerchant($merchant);
            $inventory->setWarehouse($warehouse);
            $inventory->setProductSku($sku);
            $inventory->setCurrency($item->costCurrency ?? 'CNY');
            $inventory->setQuantityAvailable($item->quantity);

            if ($item->unitCost !== null) {
                $inventory->setAverageCost($item->unitCost);
            }

            $this->entityManager->persist($inventory);

            // 记录流水
            $transaction = InventoryTransaction::createImport(
                $inventory,
                $item->quantity,
                0,
                $item->quantity,
                $item->unitCost,
                sprintf('Excel 导入，SKU: %s', $skuCode),
                $operatorId,
                $operatorName
            );
            $this->entityManager->persist($transaction);

            ++$result['imported'];
        }

        $this->entityManager->flush();

        $this->logger->info('Batch imported inventory for merchant warehouse', [
            'merchant_id' => $merchant->getId(),
            'warehouse_id' => $warehouse->getId(),
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'errors' => count($result['errors']),
        ]);

        return $result;
    }

    /**
     * 从导入更新库存.
     */
    private function updateInventoryFromImport(
        MerchantInventory $inventory,
        int $newQuantity,
        ?string $unitCost,
        ?string $costCurrency,
        ?string $operatorId,
        ?string $operatorName,
        bool $isOverride
    ): void {
        $balanceBefore = $inventory->getQuantityAvailable();
        $quantityChange = $newQuantity - $balanceBefore;

        $inventory->setQuantityAvailable($newQuantity);

        if ($unitCost !== null) {
            $inventory->setAverageCost($unitCost);
        }

        if ($costCurrency !== null) {
            $inventory->setCurrency($costCurrency);
        }

        // 记录流水
        $sku = $inventory->getProductSku();
        $skuCode = $sku->getProduct()->getStyleNumber().'-'.$sku->getSizeValue();
        $notes = $isOverride
            ? sprintf('Excel 导入（覆盖），SKU: %s', $skuCode)
            : sprintf('Excel 导入（累加），SKU: %s', $skuCode);

        $transaction = InventoryTransaction::createImport(
            $inventory,
            $quantityChange,
            $balanceBefore,
            $newQuantity,
            $unitCost,
            $notes,
            $operatorId,
            $operatorName
        );
        $this->entityManager->persist($transaction);
    }
}
