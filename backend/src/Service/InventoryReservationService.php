<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ChannelProduct;
use App\Entity\Fulfillment;
use App\Entity\FulfillmentItem;
use App\Entity\InventoryReservation;
use App\Entity\InventoryTransaction;
use App\Entity\MerchantInventory;
use App\Entity\Order;
use App\Entity\OrderException;
use App\Entity\OrderItem;
use App\Repository\InventoryReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * 库存预留服务 - 两层预留机制.
 *
 * 第一层：ChannelProduct 层预留（订单确认时）
 * 第二层：MerchantInventory 层锁定（履约分配时）
 */
class InventoryReservationService
{
    // 默认预留时长（分钟）
    private const DEFAULT_RESERVATION_TTL = 60;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InventoryReservationRepository $reservationRepository,
        private readonly BusinessNoGenerator $businessNoGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 创建 ChannelProduct 层预留（订单确认时调用）.
     *
     * @param int $ttlMinutes 预留时长（分钟）
     *
     * @throws \LogicException 库存不足时抛出
     */
    public function createChannelReservation(
        ChannelProduct $channelProduct,
        Order $order,
        OrderItem $orderItem,
        int $quantity,
        int $ttlMinutes = self::DEFAULT_RESERVATION_TTL
    ): InventoryReservation {
        // 1. 检查是否已有预留
        if ($this->reservationRepository->hasReservationForOrderItem($orderItem)) {
            $this->logger->warning('Order item already has active reservation', [
                'orderItemId' => $orderItem->getId(),
                'orderId' => $order->getId(),
            ]);

            // 返回现有预留
            $existing = $this->reservationRepository->findActiveByOrderItem($orderItem);
            if ($existing !== null) {
                return $existing;
            }
        }

        // 2. 检查 ChannelProduct 有效库存
        $effectiveStock = $channelProduct->getEffectiveStock();
        if ($effectiveStock < $quantity) {
            throw new \LogicException(sprintf('Insufficient effective stock for channel product %s: available=%d, required=%d', $channelProduct->getId(), $effectiveStock, $quantity));
        }

        // 3. 创建预留记录
        $reservation = InventoryReservation::createForChannelProduct(
            $channelProduct,
            $order,
            $orderItem,
            $quantity,
            $ttlMinutes
        );

        // 4. 更新 ChannelProduct 预留数量
        $channelProduct->reserve($quantity);

        $this->entityManager->persist($reservation);

        $this->logger->info('Channel product reservation created', [
            'reservationId' => $reservation->getId(),
            'channelProductId' => $channelProduct->getId(),
            'orderId' => $order->getId(),
            'orderItemId' => $orderItem->getId(),
            'quantity' => $quantity,
            'effectiveStockBefore' => $effectiveStock,
            'effectiveStockAfter' => $channelProduct->getEffectiveStock(),
            'expiresAt' => $reservation->getExpiresAt()->format(\DateTimeInterface::ATOM),
        ]);

        return $reservation;
    }

    /**
     * 分配库存来源（履约分配时调用）.
     *
     * @throws \LogicException 库存不足或状态异常时抛出
     */
    public function allocateReservation(
        InventoryReservation $reservation,
        MerchantInventory $inventory,
        Fulfillment $fulfillment,
        FulfillmentItem $fulfillmentItem
    ): void {
        // 验证状态
        if (!$reservation->isReserved()) {
            throw new \LogicException(sprintf('Reservation %s is not in reserved status, current: %s', $reservation->getId(), $reservation->getStatus()));
        }

        // 检查 MerchantInventory 有效可用库存
        $effectiveAvailable = $inventory->getEffectiveAvailable();
        if ($effectiveAvailable < $reservation->getQuantity()) {
            throw new \LogicException(sprintf('Insufficient effective available inventory %s: available=%d, required=%d', $inventory->getId(), $effectiveAvailable, $reservation->getQuantity()));
        }

        // 更新预留记录
        $reservation->markAllocated($inventory, $fulfillment, $fulfillmentItem);

        // 更新 MerchantInventory 待确认预留
        $inventory->pendingReserve($reservation->getQuantity());

        // 记录流水
        $this->createInventoryTransaction(
            $inventory,
            InventoryTransaction::TYPE_PENDING_RESERVE,
            -$reservation->getQuantity(),
            'pending_reserve',
            $fulfillment->getId(),
            $fulfillment->getFulfillmentNo()
        );

        $this->logger->info('Reservation allocated to inventory', [
            'reservationId' => $reservation->getId(),
            'inventoryId' => $inventory->getId(),
            'fulfillmentId' => $fulfillment->getId(),
            'quantity' => $reservation->getQuantity(),
        ]);
    }

    /**
     * 确认锁定（出库单提交时调用）.
     *
     * 软锁定 -> 硬锁定
     */
    public function lockReservation(InventoryReservation $reservation): void
    {
        // 验证状态
        if (!$reservation->isAllocated()) {
            throw new \LogicException(sprintf('Reservation %s is not in allocated status, current: %s', $reservation->getId(), $reservation->getStatus()));
        }

        $inventory = $reservation->getInventory();
        if ($inventory === null) {
            throw new \LogicException('Reservation has no inventory assigned');
        }

        $fulfillment = $reservation->getFulfillment();

        // 确认待确认预留 -> 实际锁定
        $inventory->confirmPendingReserve($reservation->getQuantity());

        // 更新预留状态
        $reservation->markLocked();

        // 记录流水
        $this->createInventoryTransaction(
            $inventory,
            InventoryTransaction::TYPE_OUTBOUND_RESERVE,
            -$reservation->getQuantity(),
            'reserved',
            $fulfillment?->getId(),
            $fulfillment?->getFulfillmentNo()
        );

        $this->logger->info('Reservation locked', [
            'reservationId' => $reservation->getId(),
            'inventoryId' => $inventory->getId(),
            'quantity' => $reservation->getQuantity(),
        ]);
    }

    /**
     * 释放预留（订单取消时调用）.
     */
    public function releaseReservation(InventoryReservation $reservation, ?string $reason = null): void
    {
        if (!$reservation->isActive()) {
            $this->logger->warning('Attempting to release inactive reservation', [
                'reservationId' => $reservation->getId(),
                'status' => $reservation->getStatus(),
            ]);

            return;
        }

        $channelProduct = $reservation->getChannelProduct();
        $inventory = $reservation->getInventory();
        $quantity = $reservation->getQuantity();

        // 根据当前状态释放对应层级
        switch ($reservation->getStatus()) {
            case InventoryReservation::STATUS_RESERVED:
                // 只释放 ChannelProduct 层预留
                $channelProduct->releaseReserve($quantity);
                break;

            case InventoryReservation::STATUS_ALLOCATED:
                // 释放 ChannelProduct 和 MerchantInventory 待确认预留
                $channelProduct->releaseReserve($quantity);
                if ($inventory !== null) {
                    $inventory->releasePendingReserve($quantity);

                    $this->createInventoryTransaction(
                        $inventory,
                        InventoryTransaction::TYPE_PENDING_RELEASE,
                        $quantity,
                        'pending_reserve',
                        $reservation->getFulfillment()?->getId(),
                        $reservation->getFulfillment()?->getFulfillmentNo(),
                        $reason
                    );
                }
                break;

            case InventoryReservation::STATUS_LOCKED:
                // 释放 ChannelProduct 预留和 MerchantInventory 锁定（reserved -> available）
                $channelProduct->releaseReserve($quantity);
                if ($inventory !== null) {
                    $inventory->releaseReserved($quantity);

                    $this->createInventoryTransaction(
                        $inventory,
                        InventoryTransaction::TYPE_OUTBOUND_RELEASE,
                        $quantity,
                        'available',
                        $reservation->getFulfillment()?->getId(),
                        $reservation->getFulfillment()?->getFulfillmentNo(),
                        $reason
                    );
                }
                break;
        }

        $reservation->markReleased();

        $this->logger->info('Reservation released', [
            'reservationId' => $reservation->getId(),
            'previousStatus' => $reservation->getStatus(),
            'quantity' => $quantity,
            'reason' => $reason,
        ]);
    }

    /**
     * 完成预留（发货后调用）.
     */
    public function completeReservation(InventoryReservation $reservation): void
    {
        if (!$reservation->isLocked()) {
            throw new \LogicException(sprintf('Reservation %s is not in locked status, current: %s', $reservation->getId(), $reservation->getStatus()));
        }

        $channelProduct = $reservation->getChannelProduct();
        $inventory = $reservation->getInventory();
        $quantity = $reservation->getQuantity();

        // 清理 ChannelProduct 预留
        $channelProduct->releaseReserve($quantity);

        // MerchantInventory 的 reserved 减少在出库发货时已处理

        // 更新预留状态
        $reservation->markCompleted();

        $this->logger->info('Reservation completed', [
            'reservationId' => $reservation->getId(),
            'quantity' => $quantity,
        ]);
    }

    /**
     * 处理过期预留（定时任务调用）.
     *
     * @return array{expired: int, exceptions: int}
     */
    public function expireReservations(int $limit = 100): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expiredReservations = $this->reservationRepository->findExpired($now, $limit);

        $stats = ['expired' => 0, 'exceptions' => 0];

        foreach ($expiredReservations as $reservation) {
            try {
                $this->expireSingleReservation($reservation);
                ++$stats['expired'];
            } catch (\Throwable $e) {
                $this->logger->error('Failed to expire reservation', [
                    'reservationId' => $reservation->getId(),
                    'error' => $e->getMessage(),
                ]);
                ++$stats['exceptions'];
            }
        }

        $this->entityManager->flush();

        return $stats;
    }

    /**
     * 处理单个过期预留.
     */
    private function expireSingleReservation(InventoryReservation $reservation): void
    {
        $channelProduct = $reservation->getChannelProduct();
        $inventory = $reservation->getInventory();
        $quantity = $reservation->getQuantity();
        $order = $reservation->getOrder();

        // 释放预留
        if ($reservation->isReserved()) {
            $channelProduct->releaseReserve($quantity);
        } elseif ($reservation->isAllocated()) {
            $channelProduct->releaseReserve($quantity);
            if ($inventory !== null) {
                $inventory->releasePendingReserve($quantity);
            }
        }

        // 标记为过期
        $reservation->markExpired();

        // 创建订单异常工单
        $this->createOrderException($order, $reservation);

        $this->logger->info('Reservation expired', [
            'reservationId' => $reservation->getId(),
            'orderId' => $order->getId(),
            'quantity' => $quantity,
        ]);
    }

    /**
     * 释放订单的所有预留（订单取消时调用）.
     */
    public function releaseOrderReservations(Order $order, ?string $reason = null): int
    {
        $reservations = $this->reservationRepository->findActiveByOrder($order);
        $count = 0;

        foreach ($reservations as $reservation) {
            $this->releaseReservation($reservation, $reason);
            ++$count;
        }

        return $count;
    }

    /**
     * 为订单的所有项目创建预留（订单确认时调用）.
     *
     * @return InventoryReservation[]
     *
     * @throws \LogicException 任一项目库存不足时抛出
     */
    public function createReservationsForOrder(Order $order, int $ttlMinutes = self::DEFAULT_RESERVATION_TTL): array
    {
        $reservations = [];

        foreach ($order->getItems() as $orderItem) {
            $channelProduct = $orderItem->getChannelProduct();
            if ($channelProduct === null) {
                $this->logger->warning('Order item has no channel product', [
                    'orderItemId' => $orderItem->getId(),
                    'orderId' => $order->getId(),
                ]);
                continue;
            }

            $reservation = $this->createChannelReservation(
                $channelProduct,
                $order,
                $orderItem,
                $orderItem->getQuantity(),
                $ttlMinutes
            );

            $reservations[] = $reservation;
        }

        return $reservations;
    }

    /**
     * 创建库存流水.
     */
    private function createInventoryTransaction(
        MerchantInventory $inventory,
        string $type,
        int $quantity,
        string $stockType,
        ?string $referenceId = null,
        ?string $referenceNo = null,
        ?string $notes = null
    ): void {
        $balanceBefore = match ($stockType) {
            'available' => $inventory->getQuantityAvailable(),
            'reserved' => $inventory->getQuantityReserved(),
            'pending_reserve' => $inventory->getQuantityPendingReserve(),
            default => 0,
        };

        $transaction = new InventoryTransaction();
        $transaction->setMerchantInventory($inventory);
        $transaction->setType($type);
        $transaction->setQuantity($quantity);
        $transaction->setStockType($stockType);
        $transaction->setBalanceBefore($balanceBefore);
        $transaction->setBalanceAfter($balanceBefore + $quantity);
        $transaction->setReferenceType(InventoryTransaction::REF_SALES_ORDER);
        $transaction->setReferenceId($referenceId);
        $transaction->setReferenceNo($referenceNo);
        $transaction->setNotes($notes);

        $this->entityManager->persist($transaction);
    }

    /**
     * 创建订单异常工单.
     */
    private function createOrderException(Order $order, InventoryReservation $reservation): void
    {
        $exception = new OrderException();
        $exception->setExceptionNo($this->businessNoGenerator->generateOrderExceptionNo());
        $exception->setOrder($order);
        $exception->setType(OrderException::TYPE_RESERVATION_EXPIRED);
        $exception->setDescription(sprintf(
            'Inventory reservation expired for order item %s (quantity: %d)',
            $reservation->getOrderItem()->getId(),
            $reservation->getQuantity()
        ));

        $this->entityManager->persist($exception);
    }
}
