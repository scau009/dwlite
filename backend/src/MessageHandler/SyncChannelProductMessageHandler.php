<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\ChannelProduct;
use App\Message\PushChannelProductMessage;
use App\Message\SyncChannelProductMessage;
use App\Repository\ChannelProductRepository;
use App\Repository\InventoryListingRepository;
use App\Service\ChannelProductSyncService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handler for SyncChannelProductMessage.
 *
 * Performs channel product aggregation:
 * 1. Acquires distributed lock to prevent concurrent processing
 * 2. Recalculates aggregated stock and price
 * 3. Dispatches PushChannelProductMessage if sync to external channel is needed
 */
#[AsMessageHandler]
class SyncChannelProductMessageHandler
{
    public function __construct(
        private ChannelProductRepository $channelProductRepo,
        private InventoryListingRepository $listingRepo,
        private ChannelProductSyncService $syncService,
        private MessageBusInterface $messageBus,
        private LockFactory $lockFactory,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncChannelProductMessage $message): void
    {
        // 检查是否应该处理此消息（延迟消息去重）
        if (!$this->syncService->shouldProcessMessage($message)) {
            $this->logger->info('Skipping outdated sync message', [
                'channelProductId' => $message->channelProductId,
                'timestamp' => $message->getDispatchTimestamp(),
            ]);

            return;
        }

        $this->logger->info('Processing channel product sync', [
            'channelProductId' => $message->channelProductId,
            'triggerSource' => $message->triggerSource,
            'inventoryListingId' => $message->inventoryListingId,
        ]);

        // Acquire distributed lock to prevent concurrent processing
        $lock = $this->lockFactory->createLock(
            sprintf('sync:processing:%s', $message->channelProductId),
            60  // TTL in seconds
        );

        if (!$lock->acquire(false)) {
            $this->logger->info('Sync already in progress, skipping', [
                'channelProductId' => $message->channelProductId,
            ]);

            return;
        }

        try {
            $this->processSync($message);
        } finally {
            $lock->release();
        }
    }

    private function processSync(SyncChannelProductMessage $message): void
    {
        // Find channel product
        $channelProduct = $this->channelProductRepo->find($message->channelProductId);
        if ($channelProduct === null) {
            $this->logger->warning('ChannelProduct not found', [
                'id' => $message->channelProductId,
            ]);

            return;
        }

        // Find trigger listing if specified
        $triggerListing = null;
        if ($message->inventoryListingId !== null) {
            $triggerListing = $this->listingRepo->find($message->inventoryListingId);
        }

        try {
            // Perform aggregation
            $syncLog = $this->syncService->aggregateChannelProduct(
                $channelProduct,
                $message->getTriggerSourceEnum(),
                $triggerListing,
                $message->merchantId,
                $message->merchantInventoryId,
            );

            $this->logger->info('Channel product aggregation completed', [
                'channelProductId' => $channelProduct->getId(),
                'syncLogId' => $syncLog->getId(),
                'syncStatus' => $channelProduct->getSyncStatus(),
            ]);

            // Check if we need to push to external channel
            if ($this->shouldPushToChannel($channelProduct)) {
                $operation = $this->determineOperation($channelProduct);

                $this->messageBus->dispatch(new PushChannelProductMessage(
                    $channelProduct->getId(),
                    $operation,
                ));

                $this->logger->info('Dispatched push to channel', [
                    'channelProductId' => $channelProduct->getId(),
                    'operation' => $operation,
                ]);
            }else{
                $this->logger->info('No push needed for channel product', [
                    'channelProductId' => $channelProduct->getId(),
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to sync channel product', [
                'channelProductId' => $channelProduct->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to let Messenger handle retry
            throw $e;
        }
    }

    /**
     * Determine if we should push to external channel.
     */
    private function shouldPushToChannel(ChannelProduct $channelProduct): bool
    {
        // Must have pending sync status
        if ($channelProduct->getSyncStatus() !== ChannelProduct::SYNC_STATUS_PENDING) {
            return false;
        }

        // Push if product is active (includes re-listing from delisted status)
        if ($channelProduct->getStatus() === ChannelProduct::STATUS_ACTIVE) {
            return true;
        }

        // Push if product is paused (to sync stock=0 to channel)
        if ($channelProduct->getStatus() === ChannelProduct::STATUS_PAUSED) {
            return true;
        }

        return false;
    }

    /**
     * Determine the push operation based on product state.
     */
    private function determineOperation(ChannelProduct $channelProduct): string
    {
        // If no external ID, this is a new product
        if ($channelProduct->getExternalId() === null) {
            return PushChannelProductMessage::OPERATION_PUSH_PRODUCT;
        }

        // Otherwise, update stock and price
        return PushChannelProductMessage::OPERATION_UPDATE_STOCK_PRICE;
    }
}
