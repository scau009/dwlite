<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\AllocateOrderMessage;
use App\Message\FulfillmentRejectionMessage;
use App\Repository\FulfillmentRepository;
use App\Service\Fulfillment\FulfillmentAllocationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class FulfillmentRejectionMessageHandler
{
    public function __construct(
        private readonly FulfillmentRepository $fulfillmentRepository,
        private readonly FulfillmentAllocationService $allocationService,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(FulfillmentRejectionMessage $message): void
    {
        $this->logger->info('Processing fulfillment rejection', [
            'fulfillmentId' => $message->fulfillmentId,
            'reason' => $message->reason,
            'isTimeout' => $message->isTimeout,
        ]);

        $fulfillment = $this->fulfillmentRepository->find($message->fulfillmentId);

        if ($fulfillment === null) {
            $this->logger->warning('Fulfillment not found', [
                'fulfillmentId' => $message->fulfillmentId,
            ]);

            return;
        }

        try {
            // 标记拒绝/超时并释放库存
            $this->allocationService->handleFulfillmentRejection(
                $fulfillment,
                $message->reason,
                $message->isTimeout
            );

            // 计算下一次分配的参数并派发重新分配
            $params = $this->allocationService->getNextAllocationParams($fulfillment);
            $order = $fulfillment->getOrder();

            $this->messageBus->dispatch(new AllocateOrderMessage(
                $order->getId(),
                $params['excludedMerchantIds'],
                $params['attemptNumber']
            ));

            $this->logger->info('Reallocation message dispatched', [
                'orderId' => $order->getId(),
                'excludedMerchantIds' => $params['excludedMerchantIds'],
                'attemptNumber' => $params['attemptNumber'],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to process fulfillment rejection', [
                'fulfillmentId' => $message->fulfillmentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
