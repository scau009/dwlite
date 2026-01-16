<?php

namespace App\MessageHandler;

use App\Message\StartProductSyncMessage;
use App\Message\SyncProductPageMessage;
use App\Repository\ProductSyncJobRepository;
use App\Service\ProductSync\ProductDataProviderRegistry;
use App\Service\ProductSync\ProductSyncService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handles the StartProductSyncMessage to initiate a sync job.
 *
 * This handler:
 * 1. Checks for existing running jobs
 * 2. Creates a new sync job
 * 3. Fetches the first page to determine total pages
 * 4. Dispatches page sync messages for async processing
 */
#[AsMessageHandler]
class StartProductSyncMessageHandler
{
    public function __construct(
        private ProductSyncJobRepository $syncJobRepository,
        private ProductDataProviderRegistry $providerRegistry,
        private ProductSyncService $syncService,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(StartProductSyncMessage $message): void
    {
        $provider = $message->provider;

        $this->logger->info('Starting product sync', [
            'provider' => $provider,
        ]);

        // Check for running job
        $runningJob = $this->syncJobRepository->findRunningByProvider($provider);
        if ($runningJob !== null) {
            $this->logger->warning('Product sync already running, skipping', [
                'provider' => $provider,
                'existing_job_id' => $runningJob->getId(),
            ]);

            return;
        }

        // Get provider
        if (!$this->providerRegistry->has($provider)) {
            $this->logger->error('Unknown product data provider', [
                'provider' => $provider,
            ]);

            return;
        }

        $dataProvider = $this->providerRegistry->get($provider);

        // Create sync job
        $job = $this->syncService->createSyncJob($provider);

        try {
            // Fetch first page to get total count
            $pageSize = 100;
            $firstPage = $dataProvider->fetchProducts(1, $pageSize);

            $totalCount = $firstPage->totalCount;
            $totalPages = $firstPage->getTotalPages();

            if ($totalCount === 0) {
                $this->logger->info('No products to sync', [
                    'provider' => $provider,
                    'job_id' => $job->getId(),
                ]);
                $job->complete();
                $this->syncJobRepository->save($job, true);

                return;
            }

            // Start job
            $this->syncService->startJob($job, $totalPages, $totalCount);

            // Dispatch page sync messages
            for ($page = 1; $page <= $totalPages; ++$page) {
                $this->messageBus->dispatch(new SyncProductPageMessage(
                    syncJobId: $job->getId(),
                    provider: $provider,
                    pageNumber: $page,
                    pageSize: $pageSize,
                ));
            }

            $this->logger->info('Dispatched page sync messages', [
                'job_id' => $job->getId(),
                'total_pages' => $totalPages,
                'total_products' => $totalCount,
            ]);
        } catch (\Throwable $e) {
            $this->syncService->failJob($job, $e->getMessage());

            $this->logger->error('Failed to start product sync', [
                'provider' => $provider,
                'job_id' => $job->getId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
