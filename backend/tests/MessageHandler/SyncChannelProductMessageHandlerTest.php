<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\ChannelProduct;
use App\Entity\ChannelProductSyncLog;
use App\Entity\InventoryListing;
use App\Message\PushChannelProductMessage;
use App\Message\SyncChannelProductMessage;
use App\MessageHandler\SyncChannelProductMessageHandler;
use App\Repository\ChannelProductRepository;
use App\Repository\InventoryListingRepository;
use App\Service\ChannelProductSyncService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class SyncChannelProductMessageHandlerTest extends TestCase
{
    private ChannelProductRepository&MockObject $channelProductRepo;
    private InventoryListingRepository&MockObject $listingRepo;
    private ChannelProductSyncService&MockObject $syncService;
    private MessageBusInterface&MockObject $messageBus;
    private LockFactory&MockObject $lockFactory;
    private LockInterface&MockObject $lock;
    private LoggerInterface&MockObject $logger;
    private SyncChannelProductMessageHandler $handler;

    protected function setUp(): void
    {
        $this->channelProductRepo = $this->createMock(ChannelProductRepository::class);
        $this->listingRepo = $this->createMock(InventoryListingRepository::class);
        $this->syncService = $this->createMock(ChannelProductSyncService::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->lock = $this->createMock(LockInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new SyncChannelProductMessageHandler(
            $this->channelProductRepo,
            $this->listingRepo,
            $this->syncService,
            $this->messageBus,
            $this->lockFactory,
            $this->logger,
        );
    }

    // ─── 1. Message deduplication ─────────────────────────────────────

    public function testSkipsOutdatedMessage(): void
    {
        $message = $this->createMessage();

        $this->syncService->expects($this->once())
            ->method('shouldProcessMessage')
            ->with($message)
            ->willReturn(false);

        // Should not attempt to acquire lock
        $this->lockFactory->expects($this->never())
            ->method('createLock');

        ($this->handler)($message);
    }

    // ─── 2. Lock contention ──────────────────────────────────────────

    public function testSkipsWhenLockCannotBeAcquired(): void
    {
        $message = $this->createMessage();

        $this->syncService->method('shouldProcessMessage')->willReturn(true);
        $this->configureLock(canAcquire: false);

        // Should not look up channel product
        $this->channelProductRepo->expects($this->never())
            ->method('find');

        ($this->handler)($message);
    }

    // ─── 3. Channel product not found ────────────────────────────────

    public function testSkipsWhenChannelProductNotFound(): void
    {
        $message = $this->createMessage();

        $this->syncService->method('shouldProcessMessage')->willReturn(true);
        $this->configureLock();

        $this->channelProductRepo->expects($this->once())
            ->method('find')
            ->with('CP001')
            ->willReturn(null);

        $this->syncService->expects($this->never())
            ->method('aggregateChannelProduct');

        // Lock should still be released
        $this->lock->expects($this->once())->method('release');

        ($this->handler)($message);
    }

    // ─── 4. Aggregation + push decision ──────────────────────────────

    public function testAggregatesAndDispatchesPushForActiveProductWithoutExternalId(): void
    {
        $message = $this->createMessage();
        $channelProduct = $this->createMockChannelProduct(
            status: ChannelProduct::STATUS_ACTIVE,
            syncStatus: ChannelProduct::SYNC_STATUS_PENDING,
            externalId: null,
        );
        $syncLog = $this->createMock(ChannelProductSyncLog::class);
        $syncLog->method('getId')->willReturn('LOG001');

        $this->syncService->method('shouldProcessMessage')->willReturn(true);
        $this->configureLock();

        $this->channelProductRepo->method('find')->willReturn($channelProduct);

        $this->syncService->expects($this->once())
            ->method('aggregateChannelProduct')
            ->willReturn($syncLog);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($msg) {
                return $msg instanceof PushChannelProductMessage
                    && $msg->channelProductId === 'CP001'
                    && $msg->operation === PushChannelProductMessage::OPERATION_PUSH_PRODUCT;
            }))
            ->willReturn(new Envelope(new PushChannelProductMessage('CP001', PushChannelProductMessage::OPERATION_PUSH_PRODUCT)));

        ($this->handler)($message);
    }

    public function testAggregatesAndDispatchesUpdateForActiveProductWithExternalId(): void
    {
        $message = $this->createMessage();
        $channelProduct = $this->createMockChannelProduct(
            status: ChannelProduct::STATUS_ACTIVE,
            syncStatus: ChannelProduct::SYNC_STATUS_PENDING,
            externalId: 'EXT-123',
        );
        $syncLog = $this->createMock(ChannelProductSyncLog::class);
        $syncLog->method('getId')->willReturn('LOG002');

        $this->syncService->method('shouldProcessMessage')->willReturn(true);
        $this->configureLock();

        $this->channelProductRepo->method('find')->willReturn($channelProduct);

        $this->syncService->expects($this->once())
            ->method('aggregateChannelProduct')
            ->willReturn($syncLog);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($msg) {
                return $msg instanceof PushChannelProductMessage
                    && $msg->channelProductId === 'CP001'
                    && $msg->operation === PushChannelProductMessage::OPERATION_UPDATE_STOCK_PRICE;
            }))
            ->willReturn(new Envelope(new PushChannelProductMessage('CP001', PushChannelProductMessage::OPERATION_UPDATE_STOCK_PRICE)));

        ($this->handler)($message);
    }

    public function testAggregatesWithoutPushWhenSyncStatusNotPending(): void
    {
        $message = $this->createMessage();
        $channelProduct = $this->createMockChannelProduct(
            status: ChannelProduct::STATUS_ACTIVE,
            syncStatus: ChannelProduct::SYNC_STATUS_SYNCED,
        );
        $syncLog = $this->createMock(ChannelProductSyncLog::class);
        $syncLog->method('getId')->willReturn('LOG004');

        $this->syncService->method('shouldProcessMessage')->willReturn(true);
        $this->configureLock();

        $this->channelProductRepo->method('find')->willReturn($channelProduct);

        $this->syncService->expects($this->once())
            ->method('aggregateChannelProduct')
            ->willReturn($syncLog);

        $this->messageBus->expects($this->never())->method('dispatch');

        ($this->handler)($message);
    }

    public function testAggregatesWithoutPushWhenStatusIsDraft(): void
    {
        $message = $this->createMessage();
        $channelProduct = $this->createMockChannelProduct(
            status: ChannelProduct::STATUS_DRAFT,
            syncStatus: ChannelProduct::SYNC_STATUS_PENDING,
        );
        $syncLog = $this->createMock(ChannelProductSyncLog::class);
        $syncLog->method('getId')->willReturn('LOG005');

        $this->syncService->method('shouldProcessMessage')->willReturn(true);
        $this->configureLock();

        $this->channelProductRepo->method('find')->willReturn($channelProduct);

        $this->syncService->expects($this->once())
            ->method('aggregateChannelProduct')
            ->willReturn($syncLog);

        $this->messageBus->expects($this->never())->method('dispatch');

        ($this->handler)($message);
    }

    public function testAggregatesWithoutPushWhenStatusIsDelisted(): void
    {
        $message = $this->createMessage();
        $channelProduct = $this->createMockChannelProduct(
            status: ChannelProduct::STATUS_DELISTED,
            syncStatus: ChannelProduct::SYNC_STATUS_PENDING,
        );
        $syncLog = $this->createMock(ChannelProductSyncLog::class);
        $syncLog->method('getId')->willReturn('LOG006');

        $this->syncService->method('shouldProcessMessage')->willReturn(true);
        $this->configureLock();

        $this->channelProductRepo->method('find')->willReturn($channelProduct);

        $this->syncService->expects($this->once())
            ->method('aggregateChannelProduct')
            ->willReturn($syncLog);

        $this->messageBus->expects($this->never())->method('dispatch');

        ($this->handler)($message);
    }

    // ─── 5. Trigger listing passthrough ──────────────────────────────

    public function testPassesTriggerListingToAggregation(): void
    {
        $message = $this->createMessage(inventoryListingId: 'LISTING-001');
        $channelProduct = $this->createMockChannelProduct(
            status: ChannelProduct::STATUS_ACTIVE,
            syncStatus: ChannelProduct::SYNC_STATUS_PENDING,
        );
        $triggerListing = $this->createMock(InventoryListing::class);
        $syncLog = $this->createMock(ChannelProductSyncLog::class);
        $syncLog->method('getId')->willReturn('LOG007');

        $this->syncService->method('shouldProcessMessage')->willReturn(true);
        $this->configureLock();

        $this->channelProductRepo->method('find')->willReturn($channelProduct);

        $this->listingRepo->expects($this->once())
            ->method('find')
            ->with('LISTING-001')
            ->willReturn($triggerListing);

        $this->syncService->expects($this->once())
            ->method('aggregateChannelProduct')
            ->with(
                $channelProduct,
                $this->anything(),
                $triggerListing,
                $this->anything(),
                $this->anything(),
            )
            ->willReturn($syncLog);

        $this->messageBus->method('dispatch')
            ->willReturn(new Envelope(new PushChannelProductMessage('CP001', PushChannelProductMessage::OPERATION_PUSH_PRODUCT)));

        ($this->handler)($message);
    }

    // ─── 6. Exception handling ───────────────────────────────────────

    public function testRethrowsExceptionFromAggregation(): void
    {
        $message = $this->createMessage();
        $channelProduct = $this->createMockChannelProduct();

        $this->syncService->method('shouldProcessMessage')->willReturn(true);
        $this->configureLock();

        $this->channelProductRepo->method('find')->willReturn($channelProduct);

        $this->syncService->expects($this->once())
            ->method('aggregateChannelProduct')
            ->willThrowException(new \RuntimeException('Aggregation failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Aggregation failed');

        ($this->handler)($message);
    }

    // ─── 7. Lock release guarantee ───────────────────────────────────

    public function testReleasesLockEvenOnException(): void
    {
        $message = $this->createMessage();
        $channelProduct = $this->createMockChannelProduct();

        $this->syncService->method('shouldProcessMessage')->willReturn(true);
        $this->configureLock();

        $this->channelProductRepo->method('find')->willReturn($channelProduct);

        $this->syncService->expects($this->once())
            ->method('aggregateChannelProduct')
            ->willThrowException(new \RuntimeException('DB error'));

        // Lock must be released even when exception occurs
        $this->lock->expects($this->once())->method('release');

        try {
            ($this->handler)($message);
        } catch (\RuntimeException) {
            // Expected — we only care that release() was called
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function createMessage(
        string $channelProductId = 'CP001',
        ?string $inventoryListingId = null,
        ?string $merchantId = null,
    ): SyncChannelProductMessage {
        return new SyncChannelProductMessage(
            channelProductId: $channelProductId,
            triggerSource: 'listing_update',
            inventoryListingId: $inventoryListingId,
            merchantId: $merchantId,
        );
    }

    private function createMockChannelProduct(
        string $id = 'CP001',
        string $status = ChannelProduct::STATUS_ACTIVE,
        string $syncStatus = ChannelProduct::SYNC_STATUS_PENDING,
        ?string $externalId = null,
    ): ChannelProduct&MockObject {
        $cp = $this->createMock(ChannelProduct::class);
        $cp->method('getId')->willReturn($id);
        $cp->method('getStatus')->willReturn($status);
        $cp->method('getSyncStatus')->willReturn($syncStatus);
        $cp->method('getExternalId')->willReturn($externalId);

        return $cp;
    }

    private function configureLock(bool $canAcquire = true): void
    {
        $this->lockFactory->method('createLock')
            ->willReturn($this->lock);

        $this->lock->method('acquire')
            ->willReturn($canAcquire);
    }
}
