<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\ProductSyncJob;
use App\Message\SyncProductBatchMessage;
use App\MessageHandler\SyncProductBatchMessageHandler;
use App\Repository\ProductSyncJobRepository;
use App\Service\ProductSync\Dto\ExternalProductDto;
use App\Service\ProductSync\Dto\PaginatedResultDto;
use App\Service\ProductSync\ProductSyncService;
use App\Service\ProductSync\Provider\KicksDbProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class SyncProductBatchMessageHandlerTest extends TestCase
{
    private ProductSyncJobRepository&MockObject $syncJobRepository;
    private KicksDbProvider&MockObject $kicksDbProvider;
    private ProductSyncService&MockObject $syncService;
    private MessageBusInterface&MockObject $messageBus;
    private LoggerInterface&MockObject $logger;
    private SyncProductBatchMessageHandler $handler;

    protected function setUp(): void
    {
        $this->syncJobRepository = $this->createMock(ProductSyncJobRepository::class);
        $this->kicksDbProvider = $this->createMock(KicksDbProvider::class);
        $this->syncService = $this->createMock(ProductSyncService::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new SyncProductBatchMessageHandler(
            $this->syncJobRepository,
            $this->kicksDbProvider,
            $this->syncService,
            $this->messageBus,
            $this->logger,
        );
    }

    public function testHandleBatchWithMoreProducts(): void
    {
        $message = new SyncProductBatchMessage(
            syncJobId: 'job-123',
            provider: KicksDbProvider::PROVIDER_NAME,
            afterRank: null,
            batchSize: 100,
            productTypeFilter: null,
            batchNumber: 1,
        );

        $job = $this->createMock(ProductSyncJob::class);
        $job->method('getId')->willReturn('job-123');
        $job->method('isRunning')->willReturn(true);
        $job->expects($this->once())->method('incrementProcessedPages');

        $this->syncJobRepository->expects($this->once())
            ->method('find')
            ->with('job-123')
            ->willReturn($job);

        $product1 = $this->createMock(ExternalProductDto::class);
        $product2 = $this->createMock(ExternalProductDto::class);

        $result = new PaginatedResultDto(
            products: [$product1, $product2],
            totalCount: 1000,
            pageNumber: 1,
            pageSize: 100,
            hasNextPage: true,
            lastRank: 12345,
        );

        $this->kicksDbProvider->expects($this->once())
            ->method('fetchProductsWithCursor')
            ->with(null, 100, null)
            ->willReturn([
                'result' => $result,
                'lastRank' => 12345,
            ]);

        $this->syncService->expects($this->exactly(2))
            ->method('syncProduct');

        $this->syncService->expects($this->once())
            ->method('flush');

        // Should dispatch next batch message
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) {
                return $message instanceof SyncProductBatchMessage
                    && $message->afterRank === 12345
                    && $message->batchNumber === 2;
            }))
            ->willReturn(new Envelope(new SyncProductBatchMessage('job-123', KicksDbProvider::PROVIDER_NAME, 12345)));

        ($this->handler)($message);
    }

    public function testHandleFinalBatchCompletesJob(): void
    {
        $message = new SyncProductBatchMessage(
            syncJobId: 'job-123',
            provider: KicksDbProvider::PROVIDER_NAME,
            afterRank: 50000,
            batchSize: 100,
            productTypeFilter: null,
            batchNumber: 500,
        );

        $job = $this->createMock(ProductSyncJob::class);
        $job->method('getId')->willReturn('job-123');
        $job->method('isRunning')->willReturn(true);
        $job->expects($this->once())->method('incrementProcessedPages');
        $job->expects($this->once())->method('complete');

        $this->syncJobRepository->expects($this->once())
            ->method('find')
            ->with('job-123')
            ->willReturn($job);

        $result = new PaginatedResultDto(
            products: [],
            totalCount: 50000,
            pageNumber: 1,
            pageSize: 100,
            hasNextPage: false,
            lastRank: null,
        );

        $this->kicksDbProvider->expects($this->once())
            ->method('fetchProductsWithCursor')
            ->willReturn([
                'result' => $result,
                'lastRank' => null,
            ]);

        $this->syncService->expects($this->once())
            ->method('flush');

        // Should NOT dispatch next batch message
        $this->messageBus->expects($this->never())
            ->method('dispatch');

        ($this->handler)($message);
    }

    public function testHandleSkipsWhenJobNotFound(): void
    {
        $message = new SyncProductBatchMessage(
            syncJobId: 'job-not-found',
            provider: KicksDbProvider::PROVIDER_NAME,
        );

        $this->syncJobRepository->expects($this->once())
            ->method('find')
            ->with('job-not-found')
            ->willReturn(null);

        $this->kicksDbProvider->expects($this->never())
            ->method('fetchProductsWithCursor');

        ($this->handler)($message);
    }

    public function testHandleSkipsWhenJobNotRunning(): void
    {
        $message = new SyncProductBatchMessage(
            syncJobId: 'job-123',
            provider: KicksDbProvider::PROVIDER_NAME,
        );

        $job = $this->createMock(ProductSyncJob::class);
        $job->method('getId')->willReturn('job-123');
        $job->method('isRunning')->willReturn(false);
        $job->method('getStatus')->willReturn('completed');

        $this->syncJobRepository->expects($this->once())
            ->method('find')
            ->with('job-123')
            ->willReturn($job);

        $this->kicksDbProvider->expects($this->never())
            ->method('fetchProductsWithCursor');

        ($this->handler)($message);
    }

    public function testHandleRejectsNonKicksDbProvider(): void
    {
        $message = new SyncProductBatchMessage(
            syncJobId: 'job-123',
            provider: 'goat',
        );

        $job = $this->createMock(ProductSyncJob::class);
        $job->method('isRunning')->willReturn(true);

        $this->syncJobRepository->expects($this->once())
            ->method('find')
            ->willReturn($job);

        $this->kicksDbProvider->expects($this->never())
            ->method('fetchProductsWithCursor');

        ($this->handler)($message);
    }
}
