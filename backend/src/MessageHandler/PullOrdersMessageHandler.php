<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PullOrdersMessage;
use App\Repository\MerchantSalesChannelRepository;
use App\Repository\SalesChannelRepository;
use App\Service\ChannelGateway\Exception\ChannelRateLimitException;
use App\Service\OrderSyncService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class PullOrdersMessageHandler
{
    private const LOCK_TTL = 300; // 5 minutes

    public function __construct(
        private readonly SalesChannelRepository $salesChannelRepo,
        private readonly MerchantSalesChannelRepository $merchantChannelRepo,
        private readonly OrderSyncService $orderSyncService,
        private readonly LockFactory $lockFactory,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(PullOrdersMessage $message): void
    {
        $this->logger->info('Processing order pull', [
            'salesChannelId' => $message->salesChannelId,
            'startTime' => $message->startTime->format(\DateTimeInterface::ATOM),
            'endTime' => $message->endTime->format(\DateTimeInterface::ATOM),
            'page' => $message->page,
        ]);

        // 获取分布式锁
        $lock = $this->lockFactory->createLock(
            sprintf('pull:orders:%s', $message->salesChannelId),
            self::LOCK_TTL
        );

        if (!$lock->acquire(false)) {
            $this->logger->info('Order pull already in progress, skipping', [
                'salesChannelId' => $message->salesChannelId,
            ]);

            return;
        }

        try {
            $this->processPull($message);
        } finally {
            $lock->release();
        }
    }

    private function processPull(PullOrdersMessage $message): void
    {
        $salesChannel = $this->salesChannelRepo->find($message->salesChannelId);
        if ($salesChannel === null || !$salesChannel->isActive()) {
            $this->logger->warning('Sales channel not found or inactive', [
                'salesChannelId' => $message->salesChannelId,
            ]);

            return;
        }

        $merchantChannel = null;
        if ($message->merchantSalesChannelId !== null) {
            $merchantChannel = $this->merchantChannelRepo->find($message->merchantSalesChannelId);
        }

        try {
            $stats = $this->orderSyncService->pullOrders(
                $salesChannel,
                $merchantChannel,
                $message->startTime,
                $message->endTime,
                $message->page,
                $message->pageSize,
            );

            $this->logger->info('Order pull completed', [
                'salesChannelId' => $message->salesChannelId,
                'page' => $message->page,
                'created' => $stats['created'],
                'updated' => $stats['updated'],
                'skipped' => $stats['skipped'],
                'errors' => $stats['errors'],
            ]);

            // 如果拉取了满页数据，可能还有更多，派发下一页
            $totalProcessed = $stats['created'] + $stats['updated'] + $stats['skipped'] + $stats['errors'];
            if ($message->hasNextPage($totalProcessed)) {
                $this->messageBus->dispatch($message->nextPage());
            }
        } catch (ChannelRateLimitException $e) {
            $this->logger->warning('Rate limited during order pull', [
                'salesChannelId' => $message->salesChannelId,
                'retryAfter' => $e->retryAfter,
            ]);
            throw $e; // 让 Messenger 重试
        } catch (\Throwable $e) {
            $this->logger->error('Failed to pull orders', [
                'salesChannelId' => $message->salesChannelId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
