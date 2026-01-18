<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ProcessConsignmentFulfillmentMessage;
use App\Repository\FulfillmentRepository;
use App\Service\Fulfillment\FulfillmentService;
use App\Service\Fulfillment\OutboundOrderCreationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * 处理寄售履约单消息处理器.
 *
 * 自动化处理寄售履约单：
 * 1. 将履约单状态从 pending 转为 processing
 * 2. 创建仓库出库单
 */
#[AsMessageHandler]
class ProcessConsignmentFulfillmentMessageHandler
{
    public function __construct(
        private readonly FulfillmentRepository $fulfillmentRepository,
        private readonly OutboundOrderCreationService $outboundOrderCreationService,
        private readonly FulfillmentService $fulfillmentService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProcessConsignmentFulfillmentMessage $message): void
    {
        $this->logger->info('Processing consignment fulfillment', [
            'fulfillmentId' => $message->fulfillmentId,
        ]);

        $fulfillment = $this->fulfillmentRepository->find($message->fulfillmentId);

        if ($fulfillment === null) {
            $this->logger->warning('Fulfillment not found', [
                'fulfillmentId' => $message->fulfillmentId,
            ]);

            return;
        }

        // 验证履约单类型和状态
        if (!$fulfillment->isPlatformWarehouse()) {
            $this->logger->warning('Fulfillment is not platform warehouse type', [
                'fulfillmentId' => $message->fulfillmentId,
                'fulfillmentType' => $fulfillment->getFulfillmentType(),
            ]);

            return;
        }

        if (!$fulfillment->isPending()) {
            $this->logger->info('Fulfillment is not in pending state, skipping', [
                'fulfillmentId' => $message->fulfillmentId,
                'status' => $fulfillment->getStatus(),
            ]);

            return;
        }

        try {
            // 1. 标记履约单为处理中
            $fulfillment->markProcessing();

            // 1.1 同步订单状态为履约中
            $this->fulfillmentService->updateOrderStatusToFulfilling($fulfillment->getOrder());

            // 2. 创建出库单
            $outboundOrder = $this->outboundOrderCreationService->createFromFulfillment($fulfillment);

            // 3. 关联出库单到履约单
            $fulfillment->setOutboundOrder($outboundOrder);

            // 4. 持久化
            $this->entityManager->persist($outboundOrder);
            $this->entityManager->flush();

            $this->logger->info('Consignment fulfillment processed successfully', [
                'fulfillmentId' => $fulfillment->getId(),
                'fulfillmentNo' => $fulfillment->getFulfillmentNo(),
                'outboundOrderId' => $outboundOrder->getId(),
                'outboundNo' => $outboundOrder->getOutboundNo(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to process consignment fulfillment', [
                'fulfillmentId' => $message->fulfillmentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
