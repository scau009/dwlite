<?php

namespace App\MessageHandler;

use App\Message\SyncProductPageMessage;
use App\Repository\ProductSyncJobRepository;
use App\Service\ProductSync\ProductDataProviderRegistry;
use App\Service\ProductSync\ProductSyncService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles the SyncProductPageMessage to sync a single page of products.
 *
 * This handler:
 * 1. Fetches the page of products from the provider
 * 2. Syncs each product using ProductSyncService
 * 3. Marks the page as processed
 */
#[AsMessageHandler]
class SyncProductPageMessageHandler
{
    public function __construct(
        private ProductSyncJobRepository $syncJobRepository,
        private ProductDataProviderRegistry $providerRegistry,
        private ProductSyncService $syncService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncProductPageMessage $message): void
    {
        $this->logger->info('Processing sync page', [
            'job_id' => $message->syncJobId,
            'provider' => $message->provider,
            'page' => $message->pageNumber,
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
            $this->logger->warning('Sync job not running, skipping page', [
                'job_id' => $job->getId(),
                'status' => $job->getStatus(),
                'page' => $message->pageNumber,
            ]);

            return;
        }

        // Get provider
        if (!$this->providerRegistry->has($message->provider)) {
            $this->logger->error('Unknown product data provider', [
                'provider' => $message->provider,
            ]);

            return;
        }

        $provider = $this->providerRegistry->get($message->provider);

        try {
            // Fetch page of products
            $result = $provider->fetchProducts($message->pageNumber, $message->pageSize);

            $this->logger->info('Fetched products for sync', [
                'job_id' => $job->getId(),
                'page' => $message->pageNumber,
                'product_count' => $result->getProductCount(),
            ]);

            // Sync each product
            foreach ($result->products as $externalProduct) {
                $this->syncService->syncProduct($job, $externalProduct);
            }

            // Flush all changes
            $this->syncService->flush();

            // Mark page as processed
            $completed = $this->syncService->markPageProcessed($job);

            $this->logger->info('Completed sync page', [
                'job_id' => $job->getId(),
                'page' => $message->pageNumber,
                'job_completed' => $completed,
                'progress' => $job->getProgressPercentage().'%',
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to sync page', [
                'job_id' => $job->getId(),
                'page' => $message->pageNumber,
                'error' => $e->getMessage(),
            ]);

            // Re-throw to let Messenger handle retry
            throw $e;
        }
    }
}
