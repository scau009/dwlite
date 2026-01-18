<?php

declare(strict_types=1);

namespace App\Service\Fulfillment;

use App\Entity\Fulfillment;
use App\Entity\Order;
use App\Message\CreateSettlementMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * 履约单完成服务 - 处理订单完成时触发履约单完成的逻辑.
 */
class FulfillmentCompletionService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * 完成订单关联的所有符合条件的履约单.
     *
     * 只有已签收（DELIVERED）状态的履约单会被标记为已完成（COMPLETED）。
     * 已取消、已拒绝、已过期的履约单保持原状态。
     *
     * @return int 完成的履约单数量
     */
    public function completeOrderFulfillments(Order $order): int
    {
        $completedCount = 0;

        foreach ($order->getFulfillments() as $fulfillment) {
            if ($this->canComplete($fulfillment)) {
                $fulfillment->markCompleted();
                ++$completedCount;

                // 派发创建结算单消息（使用 DispatchAfterCurrentBusStamp 确保消息在事务提交后才分发）
                $this->messageBus->dispatch(
                    CreateSettlementMessage::create($fulfillment->getId()),
                    [new DispatchAfterCurrentBusStamp()]
                );

                $this->logger->info('Fulfillment marked as completed', [
                    'fulfillmentId' => $fulfillment->getId(),
                    'fulfillmentNo' => $fulfillment->getFulfillmentNo(),
                    'orderId' => $order->getId(),
                ]);
            }
        }

        return $completedCount;
    }

    /**
     * 检查履约单是否可以被标记为完成.
     *
     * 只有已签收的履约单可以转换为已完成状态。
     */
    private function canComplete(Fulfillment $fulfillment): bool
    {
        return $fulfillment->isDelivered();
    }
}
