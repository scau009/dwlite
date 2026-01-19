<?php

namespace App\MessageHandler;

use App\Message\SyncProductBatchMessage;
use App\Repository\ProductSyncJobRepository;
use App\Service\ProductSync\ProductSyncService;
use App\Service\ProductSync\Provider\KicksDbProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handles the SyncProductBatchMessage to sync a batch of products using cursor-based pagination.
 *
 * This handler:
 * 1. Fetches a batch of products using cursor-based pagination
 * 2. Syncs each product using ProductSyncService
 * 3. If there are more products, dispatches the next batch message
 * 4. If no more products, marks the job as complete
 */
#[AsMessageHandler]
class SyncProductBatchMessageHandler
{
    public function __construct(
        private ProductSyncJobRepository $syncJobRepository,
        private KicksDbProvider $kicksDbProvider,
        private ProductSyncService $syncService,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncProductBatchMessage $message): void
    {
        $this->logger->info('Processing sync batch', [
            'job_id' => $message->syncJobId,
            'provider' => $message->provider,
            'batch_number' => $message->batchNumber,
            'after_rank' => $message->afterRank,
        ]);

        // Find sync job
        $job = $this->syncJobRepository->find($message->syncJobId);
        if ($job === null) {
            $this->logger->error('Sync job not found', [
                'job_id' => $message->syncJobId,
            ]);

            return;
        }

        // Check job status
        if (!$job->isRunning()) {
            $this->logger->warning('Sync job not running, skipping batch', [
                'job_id' => $job->getId(),
                'status' => $job->getStatus(),
                'batch_number' => $message->batchNumber,
            ]);

            return;
        }

        // Currently only supports KicksDB provider for cursor-based pagination
        if ($message->provider !== KicksDbProvider::PROVIDER_NAME) {
            $this->logger->error('Cursor-based pagination only supported for KicksDB provider', [
                'provider' => $message->provider,
            ]);

            return;
        }

        try {
            // Fetch batch of products using cursor-based pagination
            $response = $this->kicksDbProvider->fetchProductsWithCursor(
                afterRank: $message->afterRank,
                pageSize: $message->batchSize,
                productTypeFilter: $message->productTypeFilter,
            );

            $result = $response['result'];
            $lastRank = $response['lastRank'];

            $this->logger->info('Fetched products for sync', [
                'job_id' => $job->getId(),
                'batch_number' => $message->batchNumber,
                'product_count' => $result->getProductCount(),
                'last_rank' => $lastRank,
                'has_next_page' => $result->hasNextPage,
            ]);

            // Sync each product
            foreach ($result->products as $externalProduct) {
                $this->syncService->syncProduct($job, $externalProduct);
            }

            // Flush all changes
            $this->syncService->flush();

            // Mark batch as processed
            $job->incrementProcessedPages();
            $this->syncJobRepository->save($job, true);

            // Check if there are more products
            if ($result->hasNextPage && $lastRank !== null) {
                // Dispatch next batch message
                $this->messageBus->dispatch(new SyncProductBatchMessage(
                    syncJobId: $message->syncJobId,
                    provider: $message->provider,
                    afterRank: $lastRank,
                    batchSize: $message->batchSize,
                    productTypeFilter: $message->productTypeFilter,
                    batchNumber: $message->batchNumber + 1,
                ));

                $this->logger->debug('Dispatched next batch message', [
                    'job_id' => $job->getId(),
                    'next_batch_number' => $message->batchNumber + 1,
                    'after_rank' => $lastRank,
                ]);
            } else {
                // No more products, complete the job
                $job->complete();
                $this->syncJobRepository->save($job, true);

                $this->logger->info('Sync job completed', [
                    'job_id' => $job->getId(),
                    'total_batches' => $message->batchNumber,
                    'synced' => $job->getSyncedProducts(),
                    'created' => $job->getCreatedProducts(),
                    'updated' => $job->getUpdatedProducts(),
                    'skipped' => $job->getSkippedProducts(),
                    'failed' => $job->getFailedProducts(),
                ]);
            }

            $this->logger->info('Completed sync batch', [
                'job_id' => $job->getId(),
                'batch_number' => $message->batchNumber,
                'synced_in_batch' => $result->getProductCount(),
                'total_synced' => $job->getSyncedProducts(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to sync batch', [
                'job_id' => $job->getId(),
                'batch_number' => $message->batchNumber,
                'error' => $e->getMessage(),
            ]);

            // Re-throw to let Messenger handle retry
            throw $e;
        }
    }
}
