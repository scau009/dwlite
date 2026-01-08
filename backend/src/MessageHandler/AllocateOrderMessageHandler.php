<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\AllocateOrderMessage;
use App\Repository\OrderRepository;
use App\Service\Fulfillment\FulfillmentAllocationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class AllocateOrderMessageHandler
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly FulfillmentAllocationService $allocationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(AllocateOrderMessage $message): void
    {
        $this->logger->info('Processing order allocation', [
            'orderId' => $message->orderId,
            'attemptNumber' => $message->attemptNumber,
            'excludedMerchantIds' => $message->excludedMerchantIds,
        ]);

        $order = $this->orderRepository->find($message->orderId);

        if ($order === null) {
            $this->logger->warning('Order not found for allocation', [
                'orderId' => $message->orderId,
            ]);

            return;
        }

        // 检查订单状态是否允许分配
        if (!$order->canAllocate()) {
            $this->logger->info('Order is not in allocatable state', [
                'orderId' => $message->orderId,
                'status' => $order->getStatus(),
            ]);

            return;
        }

        try {
            $result = $this->allocationService->allocateOrder(
                $order,
                $message->excludedMerchantIds,
                $message->attemptNumber
            );

            if ($result->success) {
                $this->logger->info('Order allocation completed successfully', [
                    'orderId' => $message->orderId,
                    'fulfillmentCount' => count($result->fulfillments),
                    'hasSelfFulfillment' => $result->hasSelfFulfillment(),
                    'isAllConsignment' => $result->isAllConsignment(),
                ]);
            } else {
                $this->logger->warning('Order allocation failed', [
                    'orderId' => $message->orderId,
                    'reason' => $result->failureReason,
                    'attemptNumber' => $result->attemptNumber,
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Order allocation error', [
                'orderId' => $message->orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
