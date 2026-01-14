<?php

declare(strict_types=1);

namespace App\Service\Fulfillment;

use App\Entity\Fulfillment;
use App\Entity\Merchant;
use App\Entity\User;
use App\Message\AllocateOrderMessage;
use App\Repository\FulfillmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * 履约单业务服务 - 处理履约单的各种操作.
 */
class FulfillmentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FulfillmentRepository $fulfillmentRepository,
        private readonly FulfillmentAllocationService $allocationService,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 获取商户的履约单列表.
     *
     * @return array{data: Fulfillment[], total: int}
     */
    public function getMerchantFulfillments(
        Merchant $merchant,
        int $page = 1,
        int $limit = 20,
        ?string $status = null,
        ?string $fulfillmentType = null
    ): array {
        return $this->fulfillmentRepository->findByMerchantPaginated(
            $merchant,
            $page,
            $limit,
            $status,
            $fulfillmentType
        );
    }

    /**
     * 获取单个履约单详情（验证商户权限）.
     */
    public function getMerchantFulfillment(string $fulfillmentId, Merchant $merchant): ?Fulfillment
    {
        $fulfillment = $this->fulfillmentRepository->find($fulfillmentId);

        if ($fulfillment === null) {
            return null;
        }

        // 验证商户权限
        if ($fulfillment->getMerchant()?->getId() !== $merchant->getId()) {
            return null;
        }

        return $fulfillment;
    }

    /**
     * 商户接受履约单（自履约）.
     *
     * @throws \InvalidArgumentException
     */
    public function acceptFulfillment(Fulfillment $fulfillment, User $user): void
    {
        $this->validateMerchantFulfillment($fulfillment);

        if (!$fulfillment->isPending()) {
            throw new \InvalidArgumentException('只能接受待处理的履约单');
        }

        $fulfillment->markProcessing();

        // 更新订单状态为履约中
        $order = $fulfillment->getOrder();
        $order->markFulfilling();

        $this->entityManager->flush();

        $this->logger->info('Fulfillment accepted by merchant', [
            'fulfillmentId' => $fulfillment->getId(),
            'merchantId' => $fulfillment->getMerchant()?->getId(),
            'userId' => $user->getId(),
        ]);
    }

    /**
     * 商户拒绝履约单（自履约）.
     *
     * @throws \InvalidArgumentException
     */
    public function rejectFulfillment(Fulfillment $fulfillment, string $reason, User $user): void
    {
        $this->validateMerchantFulfillment($fulfillment);

        if (!$fulfillment->canReject()) {
            throw new \InvalidArgumentException('该履约单不能被拒绝');
        }

        // 处理拒绝
        $this->allocationService->handleFulfillmentRejection($fulfillment, $reason, false);

        $this->logger->info('Fulfillment rejected by merchant', [
            'fulfillmentId' => $fulfillment->getId(),
            'merchantId' => $fulfillment->getMerchant()?->getId(),
            'reason' => $reason,
            'userId' => $user->getId(),
        ]);

        // 触发重新分配（通过消息队列）
        $this->triggerReallocation($fulfillment);
    }

    /**
     * 商户发货（自履约）.
     *
     * @throws \InvalidArgumentException
     */
    public function shipFulfillment(
        Fulfillment $fulfillment,
        string $carrier,
        string $trackingNumber,
        ?string $trackingUrl,
        User $user
    ): void {
        $this->validateMerchantFulfillment($fulfillment);

        if (!$fulfillment->isProcessing()) {
            throw new \InvalidArgumentException('只能对处理中的履约单发货');
        }

        $fulfillment->markShipped($carrier, $trackingNumber, $trackingUrl);

        // 更新订单状态
        $order = $fulfillment->getOrder();
        $this->updateOrderStatusAfterShipping($order);

        $this->entityManager->flush();

        $this->logger->info('Fulfillment shipped by merchant', [
            'fulfillmentId' => $fulfillment->getId(),
            'merchantId' => $fulfillment->getMerchant()?->getId(),
            'carrier' => $carrier,
            'trackingNumber' => $trackingNumber,
            'userId' => $user->getId(),
        ]);
    }

    /**
     * 管理端获取履约单列表.
     *
     * @return array{data: Fulfillment[], total: int}
     */
    public function getAdminFulfillments(
        int $page = 1,
        int $limit = 20,
        ?string $status = null,
        ?string $fulfillmentType = null,
        ?string $merchantId = null,
        ?string $orderId = null
    ): array {
        return $this->fulfillmentRepository->findPaginated(
            $page,
            $limit,
            $status,
            $fulfillmentType,
            $merchantId,
            $orderId
        );
    }

    /**
     * 管理端手动重新分配.
     *
     * @throws \InvalidArgumentException
     */
    public function manualReassign(Fulfillment $fulfillment, string $reason, User $admin): void
    {
        if (!$fulfillment->isPending() && !$fulfillment->isProcessing()) {
            throw new \InvalidArgumentException('只能对待处理或处理中的履约单重新分配');
        }

        // 标记拒绝并触发重新分配
        $this->allocationService->handleFulfillmentRejection($fulfillment, 'Manual reassign: '.$reason, false);

        $this->logger->info('Fulfillment manually reassigned by admin', [
            'fulfillmentId' => $fulfillment->getId(),
            'reason' => $reason,
            'adminId' => $admin->getId(),
        ]);

        // 触发重新分配
        $this->triggerReallocation($fulfillment);
    }

    /**
     * 管理端取消履约单.
     *
     * @throws \InvalidArgumentException
     */
    public function cancelFulfillment(Fulfillment $fulfillment, string $reason, User $admin): void
    {
        if (!$fulfillment->canCancel()) {
            throw new \InvalidArgumentException('该履约单不能被取消');
        }

        $fulfillment->markCancelled($reason);

        // 释放已分配的库存
        foreach ($fulfillment->getItems() as $item) {
            $orderItem = $item->getOrderItem();
            $newAllocated = max(0, $orderItem->getAllocatedQuantity() - $item->getQuantity());
            $orderItem->setAllocatedQuantity($newAllocated);
        }

        $this->entityManager->flush();

        $this->logger->info('Fulfillment cancelled by admin', [
            'fulfillmentId' => $fulfillment->getId(),
            'reason' => $reason,
            'adminId' => $admin->getId(),
        ]);
    }

    /**
     * 处理超时的自履约订单.
     *
     * @return int 处理的履约单数量
     */
    public function handleExpiredFulfillments(): int
    {
        $expiredFulfillments = $this->fulfillmentRepository->findExpiredSelfFulfillments();
        $count = 0;

        foreach ($expiredFulfillments as $fulfillment) {
            try {
                $this->allocationService->handleFulfillmentRejection(
                    $fulfillment,
                    'Response deadline exceeded',
                    true
                );

                $this->triggerReallocation($fulfillment);
                ++$count;

                $this->logger->info('Expired fulfillment processed', [
                    'fulfillmentId' => $fulfillment->getId(),
                    'orderId' => $fulfillment->getOrder()->getId(),
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to process expired fulfillment', [
                    'fulfillmentId' => $fulfillment->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * 获取商户待处理履约单数量.
     */
    public function getMerchantPendingCount(Merchant $merchant): int
    {
        return $this->fulfillmentRepository->countByMerchantAndStatus(
            $merchant,
            Fulfillment::STATUS_PENDING
        );
    }

    /**
     * 获取商户履约统计.
     *
     * @return array{pending: int, processing: int, shipped: int, delivered: int, completed: int, rejected: int}
     */
    public function getMerchantFulfillmentStats(Merchant $merchant): array
    {
        return [
            'pending' => $this->fulfillmentRepository->countByMerchantAndStatus(
                $merchant,
                Fulfillment::STATUS_PENDING
            ),
            'processing' => $this->fulfillmentRepository->countByMerchantAndStatus(
                $merchant,
                Fulfillment::STATUS_PROCESSING
            ),
            'shipped' => $this->fulfillmentRepository->countByMerchantAndStatus(
                $merchant,
                Fulfillment::STATUS_SHIPPED
            ),
            'delivered' => $this->fulfillmentRepository->countByMerchantAndStatus(
                $merchant,
                Fulfillment::STATUS_DELIVERED
            ),
            'completed' => $this->fulfillmentRepository->countByMerchantAndStatus(
                $merchant,
                Fulfillment::STATUS_COMPLETED
            ),
            'rejected' => $this->fulfillmentRepository->countByMerchantAndStatus(
                $merchant,
                Fulfillment::STATUS_REJECTED
            ),
        ];
    }

    /**
     * 验证自履约权限.
     *
     * @throws \InvalidArgumentException
     */
    private function validateMerchantFulfillment(Fulfillment $fulfillment): void
    {
        if (!$fulfillment->isMerchantWarehouse()) {
            throw new \InvalidArgumentException('只有自履约订单可以执行此操作');
        }
    }

    /**
     * 触发重新分配.
     */
    private function triggerReallocation(Fulfillment $rejectedFulfillment): void
    {
        $params = $this->allocationService->getNextAllocationParams($rejectedFulfillment);
        $order = $rejectedFulfillment->getOrder();

        // 这里通过消息队列异步触发重新分配
        // 消息处理器会调用 FulfillmentAllocationService::allocateOrder()
        $this->messageBus->dispatch(new AllocateOrderMessage(
            $order->getId(),
            $params['excludedMerchantIds'],
            $params['attemptNumber']
        ));

        $this->logger->info('Reallocation triggered', [
            'orderId' => $order->getId(),
            'excludedMerchantIds' => $params['excludedMerchantIds'],
            'attemptNumber' => $params['attemptNumber'],
        ]);
    }

    /**
     * 发货后更新订单状态.
     */
    private function updateOrderStatusAfterShipping(\App\Entity\Order $order): void
    {
        // 检查所有履约单是否都已发货
        $allShipped = true;
        foreach ($order->getFulfillments() as $fulfillment) {
            if (!$fulfillment->isShipped() && !$fulfillment->isDelivered() && !$fulfillment->isCancelled()) {
                $allShipped = false;
                break;
            }
        }

        if ($allShipped) {
            $order->markShipped();
        }
    }
}
