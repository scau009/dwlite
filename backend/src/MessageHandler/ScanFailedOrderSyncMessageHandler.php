<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\OrderSyncState;
use App\Message\PushOrderStatusMessage;
use App\Message\ScanFailedOrderSyncMessage;
use App\Service\OrderSyncService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class ScanFailedOrderSyncMessageHandler
{
    public function __construct(
        private readonly OrderSyncService $orderSyncService,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ScanFailedOrderSyncMessage $message): void
    {
        $this->logger->info('Scanning for failed order syncs', [
            'threshold' => $message->getThreshold()->format(\DateTimeInterface::ATOM),
            'salesChannelId' => $message->salesChannelId,
            'maxRetryCount' => $message->maxRetryCount,
            'limit' => $message->limit,
        ]);

        $failedStates = $this->orderSyncService->findPendingOperations(
            $message->getThreshold(),
            $message->maxRetryCount,
            $message->limit,
        );

        if (empty($failedStates)) {
            $this->logger->info('No failed order syncs found');

            return;
        }

        $this->logger->info('Found failed order syncs', [
            'count' => count($failedStates),
        ]);

        $dispatched = 0;
        foreach ($failedStates as $syncState) {
            try {
                $operation = $this->determineOperation($syncState);
                if ($operation === null) {
                    continue;
                }

                $this->messageBus->dispatch(new PushOrderStatusMessage(
                    $syncState->getOrder()->getId(),
                    $operation,
                    $syncState->getRetryCount(),
                ));
                ++$dispatched;

                $this->logger->debug('Re-dispatched order sync', [
                    'orderId' => $syncState->getOrder()->getId(),
                    'operation' => $operation,
                    'retryCount' => $syncState->getRetryCount(),
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to re-dispatch order sync', [
                    'orderId' => $syncState->getOrder()->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Completed failed order sync scan', [
            'found' => count($failedStates),
            'dispatched' => $dispatched,
        ]);
    }

    private function determineOperation(OrderSyncState $syncState): ?string
    {
        // 优先使用 pendingOperation
        $pendingOp = $syncState->getPendingOperation();
        if ($pendingOp !== null) {
            return $pendingOp;
        }

        $order = $syncState->getOrder();

        // 如果订单已发货但未同步发货信息
        if ($order->isShipped() && $syncState->getShippedSyncCount() === 0) {
            return PushOrderStatusMessage::OP_SHIP;
        }

        // 如果订单已支付但未确认
        if ($order->isPaid() && !$syncState->isConfirmed()) {
            return PushOrderStatusMessage::OP_CONFIRM;
        }

        return null;
    }
}
