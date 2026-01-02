<?php

namespace App\Service;

use App\Dto\Inbound\AddInboundOrderItemRequest;
use App\Dto\Inbound\CompleteInboundReceivingRequest;
use App\Dto\Inbound\CreateInboundExceptionRequest;
use App\Dto\Inbound\CreateInboundOrderRequest;
use App\Dto\Inbound\ResolveInboundExceptionRequest;
use App\Dto\Inbound\ShipInboundOrderRequest;
use App\Dto\Inbound\UpdateInboundOrderItemRequest;
use App\Entity\InboundException;
use App\Entity\InboundExceptionItem;
use App\Entity\InboundOrder;
use App\Entity\InboundOrderItem;
use App\Entity\InboundShipment;
use App\Entity\Merchant;
use App\Repository\InboundExceptionRepository;
use App\Repository\InboundOrderItemRepository;
use App\Repository\InboundOrderRepository;
use App\Repository\ProductSkuRepository;
use App\Repository\WarehouseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class InboundOrderService
{
    public function __construct(
        private InboundOrderRepository $inboundOrderRepository,
        private InboundOrderItemRepository $itemRepository,
        private InboundExceptionRepository $exceptionRepository,
        private ProductSkuRepository $skuRepository,
        private WarehouseRepository $warehouseRepository,
        private InventoryService $inventoryService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * 创建入库单（草稿状态）.
     */
    public function createInboundOrder(
        Merchant $merchant,
        CreateInboundOrderRequest $dto
    ): InboundOrder {
        $warehouse = $this->warehouseRepository->find($dto->warehouseId);
        if ($warehouse === null) {
            throw new \InvalidArgumentException('Warehouse not found');
        }

        $order = new InboundOrder();
        $order->setMerchant($merchant);
        $order->setWarehouse($warehouse);
        $order->setOrderNo(InboundOrder::generateOrderNo());

        if ($dto->merchantNotes !== null) {
            $order->setMerchantNotes($dto->merchantNotes);
        }

        if ($dto->expectedArrivalDate !== null) {
            $order->setExpectedArrivalDate(\DateTimeImmutable::createFromInterface($dto->expectedArrivalDate));
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->logger->info('Created inbound order', [
            'order_id' => $order->getId(),
            'order_no' => $order->getOrderNo(),
            'merchant_id' => $merchant->getId(),
        ]);

        return $order;
    }

    /**
     * 添加商品到入库单.
     */
    public function addItem(
        InboundOrder $order,
        AddInboundOrderItemRequest $dto
    ): InboundOrderItem {
        if (!$order->isDraft()) {
            throw new \LogicException('Can only add items to draft orders');
        }

        $sku = $this->skuRepository->find($dto->productSkuId);
        if ($sku === null) {
            throw new \InvalidArgumentException('Product SKU not found');
        }

        // 检查是否已存在相同 SKU
        $existingItem = $this->itemRepository->findOneBy([
            'inboundOrder' => $order,
            'productSku' => $sku,
        ]);

        if ($existingItem !== null) {
            throw new \InvalidArgumentException('SKU already exists in this order');
        }

        $item = new InboundOrderItem();
        $item->setInboundOrder($order);
        $item->setProductSku($sku);
        $item->setExpectedQuantity($dto->expectedQuantity);

        if ($dto->unitCost !== null) {
            $item->setUnitCost($dto->unitCost);
        }

        // 保存 SKU 快照
        $item->snapshotFromSku($sku);

        $order->addItem($item);
        $order->recalculateTotals();

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        $this->logger->info('Added item to inbound order', [
            'order_id' => $order->getId(),
            'sku_id' => $sku->getId(),
            'quantity' => $dto->expectedQuantity,
        ]);

        return $item;
    }

    /**
     * 更新入库单明细.
     */
    public function updateItem(
        InboundOrderItem $item,
        UpdateInboundOrderItemRequest $dto
    ): InboundOrderItem {
        $order = $item->getInboundOrder();

        if (!$order->isDraft()) {
            throw new \LogicException('Can only update items in draft orders');
        }

        $item->setExpectedQuantity($dto->expectedQuantity);

        if ($dto->unitCost !== null) {
            $item->setUnitCost($dto->unitCost);
        }

        $order->recalculateTotals();

        $this->entityManager->flush();

        $this->logger->info('Updated inbound order item', [
            'item_id' => $item->getId(),
            'order_id' => $order->getId(),
        ]);

        return $item;
    }

    /**
     * 删除入库单明细.
     */
    public function removeItem(InboundOrderItem $item): void
    {
        $order = $item->getInboundOrder();

        if (!$order->isDraft()) {
            throw new \LogicException('Can only remove items from draft orders');
        }

        $order->removeItem($item);
        $order->recalculateTotals();

        $this->entityManager->remove($item);
        $this->entityManager->flush();

        $this->logger->info('Removed item from inbound order', [
            'item_id' => $item->getId(),
            'order_id' => $order->getId(),
        ]);
    }

    /**
     * 删除草稿入库单.
     */
    public function deleteOrder(InboundOrder $order): void
    {
        if (!$order->isDraft()) {
            throw new \LogicException('Only draft orders can be deleted');
        }

        $orderId = $order->getId();
        $orderNo = $order->getOrderNo();

        // 删除所有明细
        foreach ($order->getItems() as $item) {
            $this->entityManager->remove($item);
        }

        // 删除入库单
        $this->entityManager->remove($order);
        $this->entityManager->flush();

        $this->logger->info('Deleted inbound order', [
            'order_id' => $orderId,
            'order_no' => $orderNo,
        ]);
    }

    /**
     * 提交入库单（草稿 -> 待发货）.
     */
    public function submitOrder(InboundOrder $order): InboundOrder
    {
        if ($order->getItems()->isEmpty()) {
            throw new \LogicException('Cannot submit order without items');
        }

        $order->submit();
        $this->entityManager->flush();

        $this->logger->info('Submitted inbound order', [
            'order_id' => $order->getId(),
            'order_no' => $order->getOrderNo(),
        ]);

        return $order;
    }

    /**
     * 填写物流信息并发货（待发货 -> 已发货，增加在途库存）.
     */
    public function shipOrder(
        InboundOrder $order,
        ShipInboundOrderRequest $dto,
        ?string $operatorId = null,
        ?string $operatorName = null
    ): InboundOrder {
        // 创建物流信息
        $shipment = new InboundShipment();
        $shipment->setInboundOrder($order);
        $shipment->setCarrierCode($dto->carrierCode);
        $shipment->setTrackingNumber($dto->trackingNumber);
        $shipment->setSenderName($dto->senderName);
        $shipment->setSenderPhone($dto->senderPhone);
        $shipment->setSenderAddress($dto->senderAddress);
        $shipment->setBoxCount($dto->boxCount);

        if ($dto->carrierName !== null) {
            $shipment->setCarrierName($dto->carrierName);
        }
        if ($dto->senderProvince !== null) {
            $shipment->setSenderProvince($dto->senderProvince);
        }
        if ($dto->senderCity !== null) {
            $shipment->setSenderCity($dto->senderCity);
        }
        if ($dto->totalWeight !== null) {
            $shipment->setTotalWeight($dto->totalWeight);
        }
        if ($dto->totalVolume !== null) {
            $shipment->setTotalVolume($dto->totalVolume);
        }
        if ($dto->estimatedArrivalDate !== null) {
            $shipment->setEstimatedArrivalDate(\DateTimeImmutable::createFromInterface($dto->estimatedArrivalDate));
        }
        if ($dto->notes !== null) {
            $shipment->setNotes($dto->notes);
        }

        // 标记入库单为已发货
        $order->markAsShipped();
        $order->setShipment($shipment);

        $this->entityManager->persist($shipment);

        // 增加在途库存
        $this->inventoryService->addInTransitStock($order, $operatorId, $operatorName);

        $this->entityManager->flush();

        $this->logger->info('Shipped inbound order', [
            'order_id' => $order->getId(),
            'order_no' => $order->getOrderNo(),
            'tracking_number' => $dto->trackingNumber,
        ]);

        return $order;
    }

    /**
     * 完成收货（支持单个商品或批量收货）
     * 已发货 -> 收货中 -> 已完成/部分完成.
     */
    public function completeReceiving(
        InboundOrder $order,
        CompleteInboundReceivingRequest $dto,
        ?string $operatorId = null,
        ?string $operatorName = null
    ): InboundOrder {
        if (!$order->isShipped() && $order->getStatus() !== InboundOrder::STATUS_ARRIVED && $order->getStatus() !== InboundOrder::STATUS_RECEIVING) {
            throw new \LogicException('Can only receive shipped/arrived orders');
        }

        // 更新明细收货信息
        foreach ($dto->items as $itemDto) {
            $item = $this->itemRepository->find($itemDto->itemId);
            if ($item === null || $item->getInboundOrder()->getId() !== $order->getId()) {
                throw new \InvalidArgumentException('Invalid item ID');
            }

            $item->confirmReceived(
                $itemDto->receivedQuantity,
                $itemDto->damagedQuantity,
                $itemDto->warehouseRemark
            );
        }

        // 重新计算数量
        $order->recalculateTotals();

        if ($dto->warehouseNotes !== null) {
            $order->setWarehouseNotes($dto->warehouseNotes);
        }

        // 检查是否所有商品都已收货
        $allItemsReceived = true;
        foreach ($order->getItems() as $item) {
            if ($item->getStatus() === InboundOrderItem::STATUS_PENDING) {
                $allItemsReceived = false;
                break;
            }
        }

        if ($allItemsReceived) {
            // 所有商品已收货，确认库存入库（在途转可用）
            $this->inventoryService->confirmInbound($order, $operatorId, $operatorName);

            // 判断是否有差异
            if ($order->hasQuantityDifference()) {
                $order->setStatus(InboundOrder::STATUS_PARTIAL_COMPLETED);
            } else {
                $order->setStatus(InboundOrder::STATUS_COMPLETED);
                $order->setCompletedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            }

            // 自动创建异常单（数量差异或损坏）
            $this->autoCreateExceptions($order, $operatorId);
        } else {
            // 还有未收货商品，设置为收货中状态
            $order->setStatus(InboundOrder::STATUS_RECEIVING);
        }

        $this->entityManager->flush();

        $this->logger->info('Completed receiving for inbound order', [
            'order_id' => $order->getId(),
            'order_no' => $order->getOrderNo(),
            'status' => $order->getStatus(),
            'items_received' => count($dto->items),
            'all_items_received' => $allItemsReceived,
        ]);

        return $order;
    }

    /**
     * 创建异常单.
     */
    public function createException(
        InboundOrder $order,
        CreateInboundExceptionRequest $dto
    ): InboundException {
        $exception = InboundException::createForInboundOrder(
            $order,
            $dto->type,
            $dto->description
        );

        if ($dto->evidenceImages !== null) {
            $exception->setEvidenceImages($dto->evidenceImages);
        }

        if ($dto->reportedBy !== null) {
            $exception->setReportedBy($dto->reportedBy);
        }

        // 创建异常明细项
        foreach ($dto->items as $itemData) {
            $exceptionItem = new InboundExceptionItem();

            // 尝试通过入库单明细 ID 查找
            $orderItem = null;
            if (!empty($itemData['order_item_id'])) {
                $orderItem = $this->itemRepository->find($itemData['order_item_id']);
                if ($orderItem !== null && $orderItem->getInboundOrder()->getId() === $order->getId()) {
                    $exceptionItem->snapshotFromInboundOrderItem($orderItem);
                } else {
                    $orderItem = null;
                }
            }

            // 如果没有通过入库单明细关联，使用传入的数据
            if ($orderItem === null) {
                $exceptionItem->setSkuName($itemData['sku_name'] ?? null);
                $exceptionItem->setColorName($itemData['color_name'] ?? null);
                $exceptionItem->setProductName($itemData['product_name'] ?? null);
                $exceptionItem->setProductImage($itemData['product_image'] ?? null);
            }

            // 设置数量信息
            $exceptionItem->setQuantity($itemData['quantity'] ?? 0);

            $exception->addItem($exceptionItem);
        }

        // 重新计算汇总
        $exception->recalculateTotals();

        $this->entityManager->persist($exception);
        $this->entityManager->flush();

        $this->logger->info('Created inbound exception', [
            'exception_id' => $exception->getId(),
            'exception_no' => $exception->getExceptionNo(),
            'order_id' => $order->getId(),
            'type' => $dto->type,
            'items_count' => count($dto->items),
        ]);

        return $exception;
    }

    /**
     * 处理异常单.
     */
    public function resolveException(
        InboundException $exception,
        ResolveInboundExceptionRequest $dto,
        ?string $operatorId = null,
        ?string $operatorName = null
    ): InboundException {
        $exception->resolve(
            $dto->resolution,
            $dto->resolutionNotes,
            $dto->resolvedBy
        );

        if ($dto->claimAmount !== null) {
            $exception->setClaimAmount($dto->claimAmount);
        }

        $this->entityManager->flush();

        $this->logger->info('Resolved inbound exception', [
            'exception_id' => $exception->getId(),
            'exception_no' => $exception->getExceptionNo(),
            'resolution' => $dto->resolution,
        ]);

        // 检查关联的入库单是否可以完结
        $order = $exception->getInboundOrder();
        if ($order !== null && $order->getStatus() === InboundOrder::STATUS_PARTIAL_COMPLETED) {
            $this->tryCompleteOrder($order, $operatorId, $operatorName);
        }

        return $exception;
    }

    /**
     * 尝试完结入库单（检查是否还有待处理的异常单）.
     */
    private function tryCompleteOrder(
        InboundOrder $order,
        ?string $operatorId = null,
        ?string $operatorName = null
    ): void {
        // 检查是否还有待处理的异常单
        $hasPendingExceptions = false;
        foreach ($order->getExceptions() as $exception) {
            if ($exception->isPending()) {
                $hasPendingExceptions = true;
                break;
            }
        }

        if ($hasPendingExceptions) {
            $this->logger->info('Inbound order still has pending exceptions', [
                'order_id' => $order->getId(),
                'order_no' => $order->getOrderNo(),
            ]);

            return;
        }

        // 清除剩余的在途库存（差异部分）
        $this->inventoryService->clearRemainingInTransit($order, $operatorId, $operatorName);

        // 没有待处理的异常单，完结入库单
        $order->setStatus(InboundOrder::STATUS_COMPLETED);
        $order->setCompletedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        $this->entityManager->flush();

        $this->logger->info('Inbound order completed after all exceptions resolved', [
            'order_id' => $order->getId(),
            'order_no' => $order->getOrderNo(),
        ]);
    }

    /**
     * 取消入库单.
     */
    public function cancelOrder(
        InboundOrder $order,
        string $reason,
        ?string $operatorId = null,
        ?string $operatorName = null
    ): InboundOrder {
        // 如果已发货，需要回滚在途库存
        if ($order->isShipped()) {
            $this->inventoryService->rollbackInTransit($order, $operatorId, $operatorName);
        }

        $order->cancel($reason);
        $this->entityManager->flush();

        $this->logger->info('Cancelled inbound order', [
            'order_id' => $order->getId(),
            'order_no' => $order->getOrderNo(),
            'reason' => $reason,
        ]);

        return $order;
    }

    /**
     * 获取商户的入库单列表.
     */
    public function getMerchantOrders(
        Merchant $merchant,
        ?string $status = null,
        ?string $trackingNumber = null,
        int $limit = 50
    ): array {
        return $this->inboundOrderRepository->findByMerchant($merchant, $status, $trackingNumber, $limit);
    }

    /**
     * 获取入库单详情.
     */
    public function getOrderById(string $id): ?InboundOrder
    {
        return $this->inboundOrderRepository->find($id);
    }

    /**
     * 获取入库单明细.
     */
    public function getItemById(string $id): ?InboundOrderItem
    {
        return $this->itemRepository->find($id);
    }

    /**
     * 获取异常单详情.
     */
    public function getExceptionById(string $id): ?InboundException
    {
        return $this->exceptionRepository->find($id);
    }

    /**
     * 更新仓库备注.
     */
    public function updateWarehouseNotes(InboundOrder $order, string $notes): InboundOrder
    {
        $order->setWarehouseNotes($notes);
        $this->entityManager->flush();

        $this->logger->info('Updated warehouse notes for inbound order', [
            'order_id' => $order->getId(),
            'order_no' => $order->getOrderNo(),
        ]);

        return $order;
    }

    /**
     * 自动创建异常单（收货时数量差异或损坏）.
     *
     * 根据收货结果自动创建以下类型的异常单：
     * - 数量短少：实收 < 预报
     * - 数量超出：实收 > 预报
     * - 货物损坏：损坏数量 > 0
     */
    private function autoCreateExceptions(InboundOrder $order, ?string $reportedBy = null): void
    {
        $shortItems = [];   // 数量短少的明细
        $overItems = [];    // 数量超出的明细
        $damagedItems = []; // 有损坏的明细

        // 收集各类异常明细
        foreach ($order->getItems() as $item) {
            $expected = $item->getExpectedQuantity();
            $received = $item->getReceivedQuantity();
            $damaged = $item->getDamagedQuantity();

            // 数量短少
            if ($received < $expected) {
                $shortItems[] = [
                    'item' => $item,
                    'quantity' => $expected - $received,
                ];
            }

            // 数量超出
            if ($received > $expected) {
                $overItems[] = [
                    'item' => $item,
                    'quantity' => $received - $expected,
                ];
            }

            // 货物损坏
            if ($damaged > 0) {
                $damagedItems[] = [
                    'item' => $item,
                    'quantity' => $damaged,
                ];
            }
        }

        // 创建数量短少异常单
        if (!empty($shortItems)) {
            $this->createAutoException(
                $order,
                InboundException::TYPE_QUANTITY_SHORT,
                $shortItems,
                $reportedBy
            );
        }

        // 创建数量超出异常单
        if (!empty($overItems)) {
            $this->createAutoException(
                $order,
                InboundException::TYPE_QUANTITY_OVER,
                $overItems,
                $reportedBy
            );
        }

        // 创建货物损坏异常单
        if (!empty($damagedItems)) {
            $this->createAutoException(
                $order,
                InboundException::TYPE_DAMAGED,
                $damagedItems,
                $reportedBy
            );
        }
    }

    /**
     * 创建自动异常单.
     *
     * @param array<array{item: InboundOrderItem, quantity: int}> $items
     */
    private function createAutoException(
        InboundOrder $order,
        string $type,
        array $items,
        ?string $reportedBy = null
    ): InboundException {
        // 生成异常描述
        $description = match ($type) {
            InboundException::TYPE_QUANTITY_SHORT => sprintf(
                '收货时发现数量短少，共 %d 个 SKU 存在短少情况',
                count($items)
            ),
            InboundException::TYPE_QUANTITY_OVER => sprintf(
                '收货时发现数量超出预报，共 %d 个 SKU 存在超量情况',
                count($items)
            ),
            InboundException::TYPE_DAMAGED => sprintf(
                '收货时发现货物损坏，共 %d 个 SKU 存在损坏情况',
                count($items)
            ),
            default => '收货异常',
        };

        // 创建异常单
        $exception = InboundException::createForInboundOrder($order, $type, $description);

        if ($reportedBy !== null) {
            $exception->setReportedBy($reportedBy);
        }

        // 添加异常明细
        foreach ($items as $itemData) {
            /** @var InboundOrderItem $orderItem */
            $orderItem = $itemData['item'];

            $exceptionItem = new InboundExceptionItem();
            $exceptionItem->snapshotFromInboundOrderItem($orderItem);
            $exceptionItem->setQuantity($itemData['quantity']);

            $exception->addItem($exceptionItem);
        }

        // 重新计算汇总
        $exception->recalculateTotals();

        $this->entityManager->persist($exception);

        $this->logger->info('Auto-created inbound exception', [
            'exception_no' => $exception->getExceptionNo(),
            'order_id' => $order->getId(),
            'order_no' => $order->getOrderNo(),
            'type' => $type,
            'items_count' => count($items),
        ]);

        return $exception;
    }
}
