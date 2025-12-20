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
     * 创建入库单（草稿状态）
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
     * 添加商品到入库单
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
     * 更新入库单明细
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
     * 删除入库单明细
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
     * 提交入库单（草稿 -> 待发货）
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
     * 填写物流信息并发货（待发货 -> 已发货，增加在途库存）
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
     * 完成收货（已发货 -> 已完成，在途转在仓）
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

        // 确认库存入库（在途转可用）
        $this->inventoryService->confirmInbound($order, $operatorId, $operatorName);

        // 判断是否有差异
        if ($order->hasQuantityDifference()) {
            $order->setStatus(InboundOrder::STATUS_PARTIAL_COMPLETED);
        } else {
            $order->setStatus(InboundOrder::STATUS_COMPLETED);
            $order->setCompletedAt(new \DateTimeImmutable());
        }

        $this->entityManager->flush();

        $this->logger->info('Completed receiving for inbound order', [
            'order_id' => $order->getId(),
            'order_no' => $order->getOrderNo(),
            'status' => $order->getStatus(),
        ]);

        return $order;
    }

    /**
     * 创建异常单
     */
    public function createException(
        InboundOrder $order,
        CreateInboundExceptionRequest $dto
    ): InboundException {
        $exception = InboundException::createFromInboundItems(
            $order,
            $dto->items,
            $dto->type,
            $dto->description
        );

        if ($dto->evidenceImages !== null) {
            $exception->setEvidenceImages($dto->evidenceImages);
        }

        if ($dto->reportedBy !== null) {
            $exception->setReportedBy($dto->reportedBy);
        }

        $this->entityManager->persist($exception);
        $this->entityManager->flush();

        $this->logger->info('Created inbound exception', [
            'exception_id' => $exception->getId(),
            'exception_no' => $exception->getExceptionNo(),
            'order_id' => $order->getId(),
            'type' => $dto->type,
        ]);

        return $exception;
    }

    /**
     * 处理异常单
     */
    public function resolveException(
        InboundException $exception,
        ResolveInboundExceptionRequest $dto
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

        return $exception;
    }

    /**
     * 取消入库单
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
     * 获取商户的入库单列表
     */
    public function getMerchantOrders(
        Merchant $merchant,
        ?string $status = null,
        int $limit = 50
    ): array {
        return $this->inboundOrderRepository->findByMerchant($merchant, $status, $limit);
    }

    /**
     * 获取入库单详情
     */
    public function getOrderById(string $id): ?InboundOrder
    {
        return $this->inboundOrderRepository->find($id);
    }

    /**
     * 获取入库单明细
     */
    public function getItemById(string $id): ?InboundOrderItem
    {
        return $this->itemRepository->find($id);
    }

    /**
     * 获取异常单详情
     */
    public function getExceptionById(string $id): ?InboundException
    {
        return $this->exceptionRepository->find($id);
    }
}