<?php

namespace App\MessageHandler;

use App\Message\StartProductSyncMessage;
use App\Message\SyncProductBatchMessage;
use App\Message\SyncProductPageMessage;
use App\Repository\ProductSyncJobRepository;
use App\Service\ProductSync\ProductDataProviderRegistry;
use App\Service\ProductSync\ProductSyncService;
use App\Service\ProductSync\Provider\KicksDbProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handles the StartProductSyncMessage to initiate a sync job.
 *
 * This handler:
 * 1. Checks for existing running jobs
 * 2. Creates a new sync job
 * 3. For KicksDB: Dispatches cursor-based batch sync message
 * 4. For other providers: Fetches the first page to determine total pages,
 *    then dispatches page sync messages for async processing
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

        // Create sync job
        $job = $this->syncService->createSyncJob($provider);

        try {
            // Use cursor-based pagination for KicksDB
            if ($provider === KicksDbProvider::PROVIDER_NAME) {
                $this->startCursorBasedSync($job);

                return;
            }

            // Use page-based pagination for other providers
            $this->startPageBasedSync($job, $provider);
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

    /**
     * Start cursor-based sync for KicksDB provider.
     * Dispatches a single batch message that will chain to the next batches.
     */
    private function startCursorBasedSync(\App\Entity\ProductSyncJob $job): void
    {
        // Start job (totalPages is null for cursor-based pagination)
        $job->start();
        $this->syncJobRepository->save($job, true);

        // Dispatch first batch message (no cursor = start from beginning)
        $this->messageBus->dispatch(new SyncProductBatchMessage(
            syncJobId: $job->getId(),
            provider: KicksDbProvider::PROVIDER_NAME,
            afterRank: null,
            batchSize: 100,
            productTypeFilter: null, // Fetch all product types
            batchNumber: 1,
        ));

        $this->logger->info('Dispatched cursor-based sync start message', [
            'job_id' => $job->getId(),
            'provider' => KicksDbProvider::PROVIDER_NAME,
        ]);
    }

    /**
     * Start page-based sync for other providers.
     * Fetches first page to determine total, then dispatches all page messages.
     */
    private function startPageBasedSync(\App\Entity\ProductSyncJob $job, string $provider): void
    {
        $dataProvider = $this->providerRegistry->get($provider);

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
    }
}
