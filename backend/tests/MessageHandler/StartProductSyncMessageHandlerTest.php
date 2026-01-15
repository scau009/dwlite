<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\ProductSyncJob;
use App\Message\StartProductSyncMessage;
use App\Message\SyncProductPageMessage;
use App\MessageHandler\StartProductSyncMessageHandler;
use App\Repository\ProductSyncJobRepository;
use App\Service\ProductSync\Dto\ProductPageResult;
use App\Service\ProductSync\ProductDataProviderInterface;
use App\Service\ProductSync\ProductDataProviderRegistry;
use App\Service\ProductSync\ProductSyncService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class StartProductSyncMessageHandlerTest extends TestCase
{
    private ProductSyncJobRepository&MockObject $syncJobRepository;
    private ProductDataProviderRegistry&MockObject $providerRegistry;
    private ProductSyncService&MockObject $syncService;
    private MessageBusInterface&MockObject $messageBus;
    private LoggerInterface&MockObject $logger;
    private StartProductSyncMessageHandler $handler;

    protected function setUp(): void
    {
        $this->syncJobRepository = $this->createMock(ProductSyncJobRepository::class);
        $this->providerRegistry = $this->createMock(ProductDataProviderRegistry::class);
        $this->syncService = $this->createMock(ProductSyncService::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new StartProductSyncMessageHandler(
            $this->syncJobRepository,
            $this->providerRegistry,
            $this->syncService,
            $this->messageBus,
            $this->logger,
        );
    }

    public function testHandleSuccessfulSync(): void
    {
        $message = new StartProductSyncMessage(
            'goat',
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );

        $this->syncJobRepository->expects($this->once())
            ->method('findRunningByProvider')
            ->with('goat')
            ->willReturn(null);

        $this->providerRegistry->expects($this->once())
            ->method('has')
            ->with('goat')
            ->willReturn(true);

        $provider = $this->createMock(ProductDataProviderInterface::class);

        $firstPage = new ProductPageResult(
            products: [],
            pageNumber: 1,
            pageSize: 100,
            totalCount: 250
        );

        $provider->expects($this->once())
            ->method('fetchProducts')
            ->with(1, 100)
            ->willReturn($firstPage);

        $this->providerRegistry->expects($this->once())
            ->method('get')
            ->with('goat')
            ->willReturn($provider);

        $job = $this->createMock(ProductSyncJob::class);
        $job->method('getId')->willReturn('job-123');

        $this->syncService->expects($this->once())
            ->method('createSyncJob')
            ->with('goat')
            ->willReturn($job);

        $this->syncService->expects($this->once())
            ->method('startJob')
            ->with($job, 3, 250);

        // Should dispatch 3 page sync messages (250 / 100 = 3 pages)
        $this->messageBus->expects($this->exactly(3))
            ->method('dispatch')
            ->with($this->isInstanceOf(SyncProductPageMessage::class))
            ->willReturn(new Envelope(new SyncProductPageMessage('job-123', 'goat', 1, 100)));

        ($this->handler)($message);
    }

    public function testHandleSkipsWhenJobAlreadyRunning(): void
    {
        $message = new StartProductSyncMessage(
            'goat',
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );

        $runningJob = $this->createMock(ProductSyncJob::class);
        $runningJob->method('getId')->willReturn('job-existing');

        $this->syncJobRepository->expects($this->once())
            ->method('findRunningByProvider')
            ->willReturn($runningJob);

        $this->providerRegistry->expects($this->never())
            ->method('get');

        $this->syncService->expects($this->never())
            ->method('createSyncJob');

        ($this->handler)($message);
    }

    public function testHandleUnknownProvider(): void
    {
        $message = new StartProductSyncMessage(
            'unknown',
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );

        $this->syncJobRepository->expects($this->once())
            ->method('findRunningByProvider')
            ->willReturn(null);

        $this->providerRegistry->expects($this->once())
            ->method('has')
            ->with('unknown')
            ->willReturn(false);

        $this->syncService->expects($this->never())
            ->method('createSyncJob');

        ($this->handler)($message);
    }

    public function testHandleNoProducts(): void
    {
        $message = new StartProductSyncMessage(
            'goat',
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );

        $this->syncJobRepository->method('findRunningByProvider')->willReturn(null);
        $this->providerRegistry->method('has')->willReturn(true);

        $provider = $this->createMock(ProductDataProviderInterface::class);

        $firstPage = new ProductPageResult(
            products: [],
            pageNumber: 1,
            pageSize: 100,
            totalCount: 0
        );

        $provider->expects($this->once())
            ->method('fetchProducts')
            ->willReturn($firstPage);

        $this->providerRegistry->method('get')->willReturn($provider);

        $job = $this->createMock(ProductSyncJob::class);
        $job->expects($this->once())->method('complete');

        $this->syncService->method('createSyncJob')->willReturn($job);
        $this->syncJobRepository->expects($this->once())->method('save');

        // Should not dispatch any page messages
        $this->messageBus->expects($this->never())
            ->method('dispatch');

        ($this->handler)($message);
    }

    public function testHandleException(): void
    {
        $message = new StartProductSyncMessage(
            'goat',
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );

        $this->syncJobRepository->method('findRunningByProvider')->willReturn(null);
        $this->providerRegistry->method('has')->willReturn(true);

        $provider = $this->createMock(ProductDataProviderInterface::class);
        $provider->expects($this->once())
            ->method('fetchProducts')
            ->willThrowException(new \RuntimeException('API error'));

        $this->providerRegistry->method('get')->willReturn($provider);

        $job = $this->createMock(ProductSyncJob::class);
        $this->syncService->method('createSyncJob')->willReturn($job);

        $this->syncService->expects($this->once())
            ->method('failJob')
            ->with($job, 'API error');

        $this->expectException(\RuntimeException::class);

        ($this->handler)($message);
    }
}
