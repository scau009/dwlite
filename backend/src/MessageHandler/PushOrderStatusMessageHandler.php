<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\AllocateOrderMessage;
use App\Message\PushOrderStatusMessage;
use App\Repository\OrderRepository;
use App\Service\ChannelGateway\Exception\ChannelGatewayException;
use App\Service\InventoryReservationService;
use App\Service\OrderSync\OrderValidationService;
use App\Service\OrderSyncService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class PushOrderStatusMessageHandler
{
    private const LOCK_TTL = 120; // 2 minutes

    public function __construct(
        private readonly OrderRepository $orderRepo,
        private readonly OrderSyncService $orderSyncService,
        private readonly OrderValidationService $validationService,
        private readonly InventoryReservationService $reservationService,
        private readonly LockFactory $lockFactory,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(PushOrderStatusMessage $message): void
    {
        $this->logger->info('Processing order status push', [
            'orderId' => $message->orderId,
            'operation' => $message->operation,
            'retryCount' => $message->retryCount,
        ]);

        // 获取分布式锁
        $lock = $this->lockFactory->createLock(
            sprintf('push:order:%s', $message->orderId),
            self::LOCK_TTL
        );

        if (!$lock->acquire(false)) {
            $this->logger->info('Order push already in progress, skipping', [
                'orderId' => $message->orderId,
            ]);

            return;
        }

        try {
            $this->processPush($message);
        } finally {
            $lock->release();
        }
    }

    private function processPush(PushOrderStatusMessage $message): void
    {
        $order = $this->orderRepo->find($message->orderId);
        if ($order === null) {
            $this->logger->warning('Order not found for status push', [
                'orderId' => $message->orderId,
            ]);

            return;
        }

        // 确认操作前，检查是否有未处理异常
        if ($message->operation === PushOrderStatusMessage::OP_CONFIRM) {
            if (!$this->validationService->canConfirm($order)) {
                $this->logger->info('Order has pending exceptions, skipping confirm', [
                    'orderId' => $order->getId(),
                    'pendingExceptions' => $this->validationService->countPendingExceptions($order),
                ]);

                return;
            }
        }

        try {
            $syncLog = match ($message->operation) {
                PushOrderStatusMessage::OP_CONFIRM => $this->orderSyncService->confirmOrder($order),
                PushOrderStatusMessage::OP_SHIP => $this->orderSyncService->shipOrder($order),
                PushOrderStatusMessage::OP_CANCEL => throw new \InvalidArgumentException('Cancel not implemented yet'),
                default => throw new \InvalidArgumentException(sprintf('Unknown operation: %s', $message->operation)),
            };

            if ($syncLog->isSuccess()) {
                $this->logger->info('Order status push succeeded', [
                    'orderId' => $order->getId(),
                    'operation' => $message->operation,
                    'durationMs' => $syncLog->getDurationMs(),
                ]);

                // 确认成功后，创建库存预留并触发订单分配流程
                if ($message->operation === PushOrderStatusMessage::OP_CONFIRM) {
                    // 创建库存预留（第一层：ChannelProduct 层）
                    try {
                        $reservations = $this->reservationService->createReservationsForOrder($order);
                        $this->logger->info('Inventory reservations created for order', [
                            'orderId' => $order->getId(),
                            'reservationCount' => count($reservations),
                        ]);
                    } catch (\LogicException $e) {
                        $this->logger->error('Failed to create inventory reservations', [
                            'orderId' => $order->getId(),
                            'error' => $e->getMessage(),
                        ]);
                        // 即使预留失败，仍然尝试分配（保持向后兼容）
                    }

                    // 触发订单分配流程
                    if ($order->canAllocate()) {
                        $this->messageBus->dispatch(new AllocateOrderMessage($order->getId()));
                        $this->logger->info('Order allocation triggered after confirmation', [
                            'orderId' => $order->getId(),
                        ]);
                    }
                }
            } else {
                $this->logger->warning('Order status push failed', [
                    'orderId' => $order->getId(),
                    'operation' => $message->operation,
                    'errorCode' => $syncLog->getErrorCode(),
                    'errorMessage' => $syncLog->getErrorMessage(),
                ]);
            }
        } catch (ChannelGatewayException $e) {
            $this->logger->error('Channel gateway error during order push', [
                'orderId' => $order->getId(),
                'operation' => $message->operation,
                'error' => $e->getMessage(),
                'errorCode' => $e->getErrorCode(),
            ]);
            throw $e; // 让 Messenger 重试
        }
    }
}
