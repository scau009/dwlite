<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\ChannelProduct;
use App\Entity\ChannelProductSource;
use App\Entity\ChannelProductSyncLog;
use App\Entity\InventoryListing;
use App\Entity\Merchant;
use App\Entity\MerchantInventory;
use App\Entity\MerchantSalesChannel;
use App\Entity\Product;
use App\Entity\ProductSku;
use App\Entity\SalesChannel;
use App\Enum\SyncTriggerSourceEnum;
use App\Message\SyncChannelProductMessage;
use App\Repository\ChannelProductRepository;
use App\Repository\ChannelProductSourceRepository;
use App\Repository\InventoryListingRepository;
use App\Service\BusinessNoGenerator;
use App\Service\ChannelGateway\ChannelGatewayRegistry;
use App\Service\ChannelGateway\Exception\ChannelApiException;
use App\Service\ChannelGateway\Provider\Mock\MockGateway;
use App\Service\ChannelProductSyncService;
use App\Service\Mock\MockOrderStore;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Predis\Client as RedisClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Stub for Predis\Client to avoid deprecated addMethods() in PHPUnit 11.
 *
 * Predis uses __call() for Redis commands (get, setex, etc.),
 * so we provide a concrete subclass with trackable behavior.
 */
class RedisClientStub extends RedisClient
{
    private ?string $getReturnValue = null;
    private int $setexCallCount = 0;

    public function __construct()
    {
        // Intentionally skip parent constructor
    }

    public function __call($method, $arguments)
    {
        if ($method === 'get') {
            return $this->getReturnValue;
        }
        if ($method === 'setex') {
            ++$this->setexCallCount;

            return null;
        }

        return null;
    }

    public function stubGetReturn(?string $value): void
    {
        $this->getReturnValue = $value;
    }

    public function getSetexCallCount(): int
    {
        return $this->setexCallCount;
    }
}

class ChannelProductSyncServiceTest extends TestCase
{
    private ChannelProductRepository&MockObject $channelProductRepo;
    private ChannelProductSourceRepository&MockObject $sourceRepo;
    private InventoryListingRepository&MockObject $listingRepo;
    private ChannelGatewayRegistry&MockObject $gatewayRegistry;
    private EntityManagerInterface&MockObject $entityManager;
    private MessageBusInterface&MockObject $messageBus;
    private RedisClientStub $redis;
    private LoggerInterface&MockObject $logger;
    private BusinessNoGenerator&MockObject $businessNoGenerator;
    private ChannelProductSyncService $service;

    protected function setUp(): void
    {
        $this->channelProductRepo = $this->createMock(ChannelProductRepository::class);
        $this->sourceRepo = $this->createMock(ChannelProductSourceRepository::class);
        $this->listingRepo = $this->createMock(InventoryListingRepository::class);
        $this->gatewayRegistry = $this->createMock(ChannelGatewayRegistry::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->redis = new RedisClientStub();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->businessNoGenerator = $this->createMock(BusinessNoGenerator::class);

        $this->service = new ChannelProductSyncService(
            $this->channelProductRepo,
            $this->sourceRepo,
            $this->listingRepo,
            $this->gatewayRegistry,
            $this->entityManager,
            $this->messageBus,
            $this->redis,
            $this->logger,
            $this->businessNoGenerator,
        );
    }

    // ========================================================================
    // triggerSyncFromListing
    // ========================================================================

    public function testTriggerSyncFromListingCreatesNewChannelProduct(): void
    {
        $salesChannel = $this->createSalesChannel('MOCK');
        $productSku = $this->createProductSkuWithProduct();
        $listing = $this->createMockListing('LST001', InventoryListing::STATUS_ACTIVE, '99.00', $salesChannel, $productSku);

        // No existing channel product
        $this->channelProductRepo->method('findOneByChannelAndSku')
            ->willReturn(null);

        $this->businessNoGenerator->method('generateChannelProductId')
            ->willReturn('CP001');
        $this->businessNoGenerator->method('generateChannelProductSourceId')
            ->willReturn('CPS001');

        // Source not found => create new
        $this->sourceRepo->method('findOneByProductAndListing')
            ->willReturn(null);

        $this->entityManager->expects($this->atLeast(2))
            ->method('persist');
        $this->entityManager->expects($this->atLeast(1))
            ->method('flush');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($message, $stamps) {
                $this->assertInstanceOf(SyncChannelProductMessage::class, $message);
                $this->assertSame('CP001', $message->channelProductId);
                $this->assertSame('LST001', $message->inventoryListingId);

                return new Envelope($message);
            });

        $this->service->triggerSyncFromListing(
            $listing,
            SyncTriggerSourceEnum::LISTING_CREATE,
        );

        $this->assertSame(1, $this->redis->getSetexCallCount());
    }

    public function testTriggerSyncFromListingWithExistingChannelProduct(): void
    {
        $salesChannel = $this->createSalesChannel('MOCK');
        $productSku = $this->createProductSkuWithProduct();
        $listing = $this->createMockListing('LST001', InventoryListing::STATUS_ACTIVE, '99.00', $salesChannel, $productSku);

        $existingCp = $this->createChannelProduct('CP_EXIST', $salesChannel, $productSku);

        $this->channelProductRepo->method('findOneByChannelAndSku')
            ->willReturn($existingCp);

        // Source already exists
        $source = new ChannelProductSource();
        $source->setId('CPS_EXIST');
        $source->setChannelProduct($existingCp);
        $source->setInventoryListing($listing);
        $source->setIsActive(true);

        $this->sourceRepo->method('findOneByProductAndListing')
            ->willReturn($source);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($message) {
                $this->assertInstanceOf(SyncChannelProductMessage::class, $message);
                $this->assertSame('CP_EXIST', $message->channelProductId);

                return new Envelope($message);
            });

        $this->service->triggerSyncFromListing(
            $listing,
            SyncTriggerSourceEnum::LISTING_UPDATE,
        );

        $this->assertSame(1, $this->redis->getSetexCallCount());
    }

    public function testTriggerSyncFromListingCreatesSourceWhenNotExist(): void
    {
        $salesChannel = $this->createSalesChannel('MOCK');
        $productSku = $this->createProductSkuWithProduct();
        $listing = $this->createMockListing('LST001', InventoryListing::STATUS_ACTIVE, '99.00', $salesChannel, $productSku);

        $existingCp = $this->createChannelProduct('CP001', $salesChannel, $productSku);
        $this->channelProductRepo->method('findOneByChannelAndSku')->willReturn($existingCp);

        // No existing source => should create one
        $this->sourceRepo->method('findOneByProductAndListing')->willReturn(null);

        $this->businessNoGenerator->method('generateChannelProductSourceId')->willReturn('CPS_NEW');

        $persisted = [];
        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$persisted) {
                $persisted[] = $entity;
            });
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(fn ($msg) => new Envelope($msg));

        $this->service->triggerSyncFromListing(
            $listing,
            SyncTriggerSourceEnum::LISTING_CREATE,
        );

        // Verify a ChannelProductSource was persisted
        $sourcesPersisted = array_filter($persisted, fn ($e) => $e instanceof ChannelProductSource);
        $this->assertCount(1, $sourcesPersisted);
        $source = reset($sourcesPersisted);
        $this->assertSame('CPS_NEW', $source->getId());
        $this->assertTrue($source->isActive());
    }

    public function testTriggerSyncFromListingUpdatesSourceStatusWhenMismatch(): void
    {
        $salesChannel = $this->createSalesChannel('MOCK');
        $productSku = $this->createProductSkuWithProduct();
        // Listing is paused
        $listing = $this->createMockListing('LST001', InventoryListing::STATUS_PAUSED, '99.00', $salesChannel, $productSku);

        $existingCp = $this->createChannelProduct('CP001', $salesChannel, $productSku);
        $this->channelProductRepo->method('findOneByChannelAndSku')->willReturn($existingCp);

        // Source is currently active, but listing is paused => should update
        $source = new ChannelProductSource();
        $source->setId('CPS001');
        $source->setChannelProduct($existingCp);
        $source->setInventoryListing($listing);
        $source->setIsActive(true); // Mismatched: active source but paused listing

        $this->sourceRepo->method('findOneByProductAndListing')
            ->willReturn($source);

        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(fn ($msg) => new Envelope($msg));

        $this->service->triggerSyncFromListing(
            $listing,
            SyncTriggerSourceEnum::LISTING_PAUSE,
        );

        // Source should now be inactive
        $this->assertFalse($source->isActive());
    }

    // ========================================================================
    // triggerSyncFromInventory
    // ========================================================================

    public function testTriggerSyncFromInventoryDispatchesForActiveListings(): void
    {
        $salesChannel = $this->createSalesChannel('MOCK');
        $productSku = $this->createProductSkuWithProduct();
        $listing = $this->createMockListing('LST001', InventoryListing::STATUS_ACTIVE, '50.00', $salesChannel, $productSku);
        $inventory = $listing->getMerchantInventory();

        $cp = $this->createChannelProduct('CP001', $salesChannel, $productSku);

        $this->listingRepo->method('findByInventory')->willReturn([$listing]);
        $this->channelProductRepo->method('findOneByChannelAndSku')->willReturn($cp);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($message) {
                $this->assertInstanceOf(SyncChannelProductMessage::class, $message);
                $this->assertSame('CP001', $message->channelProductId);
                $this->assertSame('INV001', $message->merchantInventoryId);

                return new Envelope($message);
            });

        $this->service->triggerSyncFromInventory(
            $inventory,
            SyncTriggerSourceEnum::INVENTORY_INBOUND,
        );
    }

    public function testTriggerSyncFromInventorySkipsInactiveListings(): void
    {
        $salesChannel = $this->createSalesChannel('MOCK');
        $productSku = $this->createProductSkuWithProduct();
        $listing = $this->createMockListing('LST001', InventoryListing::STATUS_PAUSED, '50.00', $salesChannel, $productSku);
        $inventory = $listing->getMerchantInventory();

        $this->listingRepo->method('findByInventory')->willReturn([$listing]);

        $this->messageBus->expects($this->never())->method('dispatch');

        $this->service->triggerSyncFromInventory(
            $inventory,
            SyncTriggerSourceEnum::INVENTORY_INBOUND,
        );
    }

    public function testTriggerSyncFromInventorySkipsWhenNoChannelProductFound(): void
    {
        $salesChannel = $this->createSalesChannel('MOCK');
        $productSku = $this->createProductSkuWithProduct();
        $listing = $this->createMockListing('LST001', InventoryListing::STATUS_ACTIVE, '50.00', $salesChannel, $productSku);
        $inventory = $listing->getMerchantInventory();

        $this->listingRepo->method('findByInventory')->willReturn([$listing]);
        // No channel product found for this listing
        $this->channelProductRepo->method('findOneByChannelAndSku')->willReturn(null);

        $this->messageBus->expects($this->never())->method('dispatch');

        $this->service->triggerSyncFromInventory(
            $inventory,
            SyncTriggerSourceEnum::INVENTORY_INBOUND,
        );
    }

    public function testTriggerSyncFromInventoryDispatchesForMultipleListings(): void
    {
        $salesChannel1 = $this->createSalesChannel('MOCK');
        $salesChannel2 = $this->createSalesChannel('KICKSCREW');
        $productSku = $this->createProductSkuWithProduct();

        $listing1 = $this->createMockListing('LST001', InventoryListing::STATUS_ACTIVE, '50.00', $salesChannel1, $productSku);
        $listing2 = $this->createMockListing('LST002', InventoryListing::STATUS_ACTIVE, '60.00', $salesChannel2, $productSku);
        $inventory = $listing1->getMerchantInventory();

        $cp1 = $this->createChannelProduct('CP001', $salesChannel1, $productSku);
        $cp2 = $this->createChannelProduct('CP002', $salesChannel2, $productSku);

        $this->listingRepo->method('findByInventory')->willReturn([$listing1, $listing2]);
        $this->channelProductRepo->method('findOneByChannelAndSku')
            ->willReturnMap([
                [$salesChannel1, $productSku, $cp1],
                [$salesChannel2, $productSku, $cp2],
            ]);

        $this->messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(fn ($msg) => new Envelope($msg));

        $this->service->triggerSyncFromInventory(
            $inventory,
            SyncTriggerSourceEnum::INVENTORY_INBOUND,
        );
    }

    // ========================================================================
    // triggerSyncFromChannelProduct
    // ========================================================================

    public function testTriggerSyncFromChannelProductDispatchesMessage(): void
    {
        $salesChannel = $this->createSalesChannel('MOCK');
        $productSku = $this->createProductSkuWithProduct();
        $cp = $this->createChannelProduct('CP001', $salesChannel, $productSku);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($message) {
                $this->assertInstanceOf(SyncChannelProductMessage::class, $message);
                $this->assertSame('CP001', $message->channelProductId);
                $this->assertSame('M001', $message->merchantId);

                return new Envelope($message);
            });

        $this->service->triggerSyncFromChannelProduct(
            $cp,
            SyncTriggerSourceEnum::MANUAL,
            'M001',
        );
    }

    public function testTriggerSyncFromChannelProductWithoutMerchantId(): void
    {
        $salesChannel = $this->createSalesChannel('MOCK');
        $productSku = $this->createProductSkuWithProduct();
        $cp = $this->createChannelProduct('CP001', $salesChannel, $productSku);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($message) {
                $this->assertInstanceOf(SyncChannelProductMessage::class, $message);
                $this->assertNull($message->merchantId);

                return new Envelope($message);
            });

        $this->service->triggerSyncFromChannelProduct(
            $cp,
            SyncTriggerSourceEnum::LISTING_DELETE,
        );
    }

    // ========================================================================
    // shouldProcessMessage
    // ========================================================================

    public function testShouldProcessMessageReturnsTrueWhenNoStoredTimestamp(): void
    {
        $message = SyncChannelProductMessage::create(
            'CP001',
            SyncTriggerSourceEnum::MANUAL,
            dispatchTimestamp: '1234567890.1234',
        );

        $this->redis->stubGetReturn(null);

        $this->assertTrue($this->service->shouldProcessMessage($message));
    }

    public function testShouldProcessMessageReturnsTrueWhenTimestampMatches(): void
    {
        $timestamp = '1234567890.1234';
        $message = SyncChannelProductMessage::create(
            'CP001',
            SyncTriggerSourceEnum::MANUAL,
            dispatchTimestamp: $timestamp,
        );

        $this->redis->stubGetReturn($timestamp);

        $this->assertTrue($this->service->shouldProcessMessage($message));
    }

    public function testShouldProcessMessageReturnsFalseWhenOutdated(): void
    {
        $message = SyncChannelProductMessage::create(
            'CP001',
            SyncTriggerSourceEnum::MANUAL,
            dispatchTimestamp: '1234567890.0000',
        );

        // Stored timestamp is newer
        $this->redis->stubGetReturn('1234567899.9999');

        $this->assertFalse($this->service->shouldProcessMessage($message));
    }

    // ========================================================================
    // syncAllSourceStatuses
    // ========================================================================

    public function testSyncAllSourceStatusesCorrectsMismatchedSources(): void
    {
        $salesChannel = $this->createSalesChannel('MOCK');
        $productSku = $this->createProductSkuWithProduct();
        $cp = $this->createChannelProduct('CP001', $salesChannel, $productSku);

        // Source1: active source but listing is paused => should deactivate
        $listing1 = $this->createMockListing('LST001', InventoryListing::STATUS_PAUSED, '50.00', $salesChannel, $productSku);
        $source1 = new ChannelProductSource();
        $source1->setId('CPS001');
        $source1->setChannelProduct($cp);
        $source1->setInventoryListing($listing1);
        $source1->setIsActive(true);

        // Source2: inactive source but listing is active => should activate
        $listing2 = $this->createMockListing('LST002', InventoryListing::STATUS_ACTIVE, '60.00', $salesChannel, $productSku);
        $source2 = new ChannelProductSource();
        $source2->setId('CPS002');
        $source2->setChannelProduct($cp);
        $source2->setInventoryListing($listing2);
        $source2->setIsActive(false);

        $cp->addSource($source1);
        $cp->addSource($source2);

        $this->entityManager->expects($this->once())->method('flush');

        $corrected = $this->service->syncAllSourceStatuses($cp);

        $this->assertSame(2, $corrected);
        $this->assertFalse($source1->isActive());
        $this->assertTrue($source2->isActive());
    }

    public function testSyncAllSourceStatusesNoCorrectionNeeded(): void
    {
        $salesChannel = $this->createSalesChannel('MOCK');
        $productSku = $this->createProductSkuWithProduct();
        $cp = $this->createChannelProduct('CP001', $salesChannel, $productSku);

        $listing = $this->createMockListing('LST001', InventoryListing::STATUS_ACTIVE, '50.00', $salesChannel, $productSku);
        $source = new ChannelProductSource();
        $source->setId('CPS001');
        $source->setChannelProduct($cp);
        $source->setInventoryListing($listing);
        $source->setIsActive(true);

        $cp->addSource($source);

        $this->entityManager->expects($this->never())->method('flush');

        $corrected = $this->service->syncAllSourceStatuses($cp);

        $this->assertSame(0, $corrected);
    }

    // ========================================================================
    // aggregateChannelProduct
    // ========================================================================

    public function testAggregateActiveChannelProductSuccess(): void
    {
        $salesChannel = $this->createSalesChannel('MOCK');
        $productSku = $this->createProductSkuWithProduct();
        $cp = $this->createChannelProduct('CP001', $salesChannel, $productSku);
        $cp->setStatus(ChannelProduct::STATUS_ACTIVE);
        $cp->setPlatformPrice('100.00');
        $cp->setStockMode(ChannelProduct::STOCK_MODE_LOWEST);

        $listing = $this->createMockListing('LST001', InventoryListing::STATUS_ACTIVE, '80.00', $salesChannel, $productSku);
        $source = new ChannelProductSource();
        $source->setId('CPS001');
        $source->setChannelProduct($cp);
        $source->setInventoryListing($listing);
        $source->setIsActive(true);
        $cp->addSource($source);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $syncLog = $this->service->aggregateChannelProduct(
            $cp,
            SyncTriggerSourceEnum::LISTING_UPDATE,
            $listing,
            'M001',
        );

        $this->assertTrue($syncLog->isSuccess());
        $this->assertSame(ChannelProductSyncLog::OPERATION_AGGREGATE, $syncLog->getOperation());
        // Active CP should be marked as needs sync
        $this->assertTrue($cp->needsSync());
        // Price should be recalculated to lowest
        $this->assertSame('80.00', $cp->getPlatformPrice());
    }

    public function testAggregateDraftChannelProductKeepsSyncStatus(): void
    {
        $salesChannel = $this->createSalesChannel('MOCK');
        $productSku = $this->createProductSkuWithProduct();
        $cp = $this->createChannelProduct('CP001', $salesChannel, $productSku);
        $cp->setStatus(ChannelProduct::STATUS_DRAFT);
        $cp->setSyncStatus(ChannelProduct::SYNC_STATUS_SYNCED);

        $listing = $this->createMockListing('LST001', InventoryListing::STATUS_ACTIVE, '80.00', $salesChannel, $productSku);
        $source = new ChannelProductSource();
        $source->setId('CPS001');
        $source->setChannelProduct($cp);
        $source->setInventoryListing($listing);
        $source->setIsActive(true);
        $cp->addSource($source);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $syncLog = $this->service->aggregateChannelProduct(
            $cp,
            SyncTriggerSourceEnum::LISTING_CREATE,
        );

        $this->assertTrue($syncLog->isSuccess());
        // Draft CP should NOT be marked as needsSync
        $this->assertSame(ChannelProduct::SYNC_STATUS_SYNCED, $cp->getSyncStatus());
    }

    public function testAggregateWithLowestPriceMode(): void
    {
        $salesChannel = $this->createSalesChannel('MOCK');
        $productSku = $this->createProductSkuWithProduct();
        $cp = $this->createChannelProduct('CP001', $salesChannel, $productSku);
        $cp->setStatus(ChannelProduct::STATUS_ACTIVE);
        $cp->setStockMode(ChannelProduct::STOCK_MODE_LOWEST);

        // Source 1: price 50.00
        $listing1 = $this->createMockListing('LST001', InventoryListing::STATUS_ACTIVE, '50.00', $salesChannel, $productSku, 10);
        $source1 = new ChannelProductSource();
        $source1->setId('CPS001');
        $source1->setChannelProduct($cp);
        $source1->setInventoryListing($listing1);
        $source1->setIsActive(true);
        $cp->addSource($source1);

        // Source 2: price 80.00 (higher)
        $listing2 = $this->createMockListing('LST002', InventoryListing::STATUS_ACTIVE, '80.00', $salesChannel, $productSku, 20);
        $source2 = new ChannelProductSource();
        $source2->setId('CPS002');
        $source2->setChannelProduct($cp);
        $source2->setInventoryListing($listing2);
        $source2->setIsActive(true);
        $cp->addSource($source2);

        // Source 3: same lowest price 50.00
        $listing3 = $this->createMockListing('LST003', InventoryListing::STATUS_ACTIVE, '50.00', $salesChannel, $productSku, 5);
        $source3 = new ChannelProductSource();
        $source3->setId('CPS003');
        $source3->setChannelProduct($cp);
        $source3->setInventoryListing($listing3);
        $source3->setIsActive(true);
        $cp->addSource($source3);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $syncLog = $this->service->aggregateChannelProduct(
            $cp,
            SyncTriggerSourceEnum::LISTING_UPDATE,
        );

        $this->assertTrue($syncLog->isSuccess());
        // Price should be lowest: 50.00
        $this->assertSame('50.00', $cp->getPlatformPrice());
        // Stock in "lowest" mode: sum of quantities at the lowest price = 10 + 5 = 15
        $this->assertSame(15, $cp->getStockQuantity());
    }

    public function testAggregateNoActiveSourcesSetsZero(): void
    {
        $salesChannel = $this->createSalesChannel('MOCK');
        $productSku = $this->createProductSkuWithProduct();
        $cp = $this->createChannelProduct('CP001', $salesChannel, $productSku);
        $cp->setStatus(ChannelProduct::STATUS_ACTIVE);
        $cp->setPlatformPrice('100.00');
        $cp->setStockQuantity(50);

        // Source is inactive
        $listing = $this->createMockListing('LST001', InventoryListing::STATUS_PAUSED, '80.00', $salesChannel, $productSku);
        $source = new ChannelProductSource();
        $source->setId('CPS001');
        $source->setChannelProduct($cp);
        $source->setInventoryListing($listing);
        $source->setIsActive(false);
        $cp->addSource($source);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $syncLog = $this->service->aggregateChannelProduct(
            $cp,
            SyncTriggerSourceEnum::LISTING_PAUSE,
        );

        $this->assertTrue($syncLog->isSuccess());
        $this->assertSame('0.00', $cp->getPlatformPrice());
        $this->assertSame(0, $cp->getStockQuantity());
    }

    public function testAggregateSelfHealsSourceStatus(): void
    {
        $salesChannel = $this->createSalesChannel('MOCK');
        $productSku = $this->createProductSkuWithProduct();
        $cp = $this->createChannelProduct('CP001', $salesChannel, $productSku);
        $cp->setStatus(ChannelProduct::STATUS_ACTIVE);

        // Source isActive=true but listing is paused => aggregation should self-heal via syncAllSourceStatuses
        $listing = $this->createMockListing('LST001', InventoryListing::STATUS_PAUSED, '50.00', $salesChannel, $productSku);
        $source = new ChannelProductSource();
        $source->setId('CPS001');
        $source->setChannelProduct($cp);
        $source->setInventoryListing($listing);
        $source->setIsActive(true); // Mismatched
        $cp->addSource($source);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $syncLog = $this->service->aggregateChannelProduct(
            $cp,
            SyncTriggerSourceEnum::COMPENSATION,
        );

        $this->assertTrue($syncLog->isSuccess());
        // Source should have been corrected to inactive
        $this->assertFalse($source->isActive());
        // No active sources => zero stock/price
        $this->assertSame(0, $cp->getStockQuantity());
        $this->assertSame('0.00', $cp->getPlatformPrice());
    }

    // ========================================================================
    // pushToChannel — using real MockGateway
    // ========================================================================

    public function testPushProductSuccess(): void
    {
        $salesChannel = $this->createSalesChannel('MOCK');
        $productSku = $this->createProductSkuWithProduct();
        $cp = $this->createChannelProduct('CP001', $salesChannel, $productSku);
        $cp->setStatus(ChannelProduct::STATUS_ACTIVE);
        $cp->setPlatformPrice('99.00');
        $cp->setStockQuantity(10);

        $gateway = $this->createRealMockGateway();
        $this->gatewayRegistry->method('has')->willReturn(true);
        $this->gatewayRegistry->method('get')->willReturn($gateway);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $syncLog = $this->service->pushToChannel(
            $cp,
            ChannelProductSyncLog::OPERATION_PUSH_PRODUCT,
        );

        $this->assertTrue($syncLog->isSuccess());
        $this->assertNotNull($cp->getExternalId());
        $this->assertStringStartsWith('MOCK_', $cp->getExternalId());
        $this->assertNotNull($cp->getExternalUrl());
        $this->assertTrue($cp->isSynced());
    }

    public function testPushProductPreservesExistingExternalId(): void
    {
        $salesChannel = $this->createSalesChannel('MOCK');
        $productSku = $this->createProductSkuWithProduct();
        $cp = $this->createChannelProduct('CP001', $salesChannel, $productSku);
        $cp->setStatus(ChannelProduct::STATUS_ACTIVE);
        $cp->setPlatformPrice('99.00');
        $cp->setExternalId('EXISTING_EXT_ID');

        $gateway = $this->createRealMockGateway();
        $this->gatewayRegistry->method('has')->willReturn(true);
        $this->gatewayRegistry->method('get')->willReturn($gateway);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $syncLog = $this->service->pushToChannel(
            $cp,
            ChannelProductSyncLog::OPERATION_PUSH_PRODUCT,
        );

        $this->assertTrue($syncLog->isSuccess());
        // MockGateway uses existing externalId if provided
        $this->assertSame('EXISTING_EXT_ID', $cp->getExternalId());
    }

    public function testUpdateStockPriceSuccess(): void
    {
        $salesChannel = $this->createSalesChannel('MOCK');
        $productSku = $this->createProductSkuWithProduct();
        $cp = $this->createChannelProduct('CP001', $salesChannel, $productSku);
        $cp->setStatus(ChannelProduct::STATUS_ACTIVE);
        $cp->setPlatformPrice('55.00');
        $cp->setStockQuantity(5);
        $cp->setExternalId('EXT001');

        $gateway = $this->createRealMockGateway();
        $this->gatewayRegistry->method('has')->willReturn(true);
        $this->gatewayRegistry->method('get')->willReturn($gateway);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $syncLog = $this->service->pushToChannel(
            $cp,
            ChannelProductSyncLog::OPERATION_UPDATE_STOCK_PRICE,
        );

        $this->assertTrue($syncLog->isSuccess());
        $this->assertTrue($cp->isSynced());
    }

    public function testDelistProductSuccess(): void
    {
        $salesChannel = $this->createSalesChannel('MOCK');
        $productSku = $this->createProductSkuWithProduct();
        $cp = $this->createChannelProduct('CP001', $salesChannel, $productSku);
        $cp->setStatus(ChannelProduct::STATUS_DELISTED);
        $cp->setExternalId('EXT001');

        $gateway = $this->createRealMockGateway();
        $this->gatewayRegistry->method('has')->willReturn(true);
        $this->gatewayRegistry->method('get')->willReturn($gateway);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $syncLog = $this->service->pushToChannel(
            $cp,
            ChannelProductSyncLog::OPERATION_DELIST,
        );

        $this->assertTrue($syncLog->isSuccess());
        $this->assertTrue($cp->isSynced());
    }

    public function testPushToChannelNoGatewayMarksSkipped(): void
    {
        $salesChannel = $this->createSalesChannel('UNKNOWN_CHANNEL');
        $productSku = $this->createProductSkuWithProduct();
        $cp = $this->createChannelProduct('CP001', $salesChannel, $productSku);

        $this->gatewayRegistry->method('has')->willReturn(false);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $syncLog = $this->service->pushToChannel(
            $cp,
            ChannelProductSyncLog::OPERATION_PUSH_PRODUCT,
        );

        $this->assertTrue($syncLog->isSkipped());
        $this->assertSame(ChannelProduct::SYNC_STATUS_FAILED, $cp->getSyncStatus());
    }

    public function testPushToChannelGatewayFailureMarksFailed(): void
    {
        // Configure SalesChannel to trigger simulated failure
        $salesChannel = $this->createSalesChannel('MOCK', [
            'simulate_failure' => ['value' => true],
        ]);
        $productSku = $this->createProductSkuWithProduct();
        $cp = $this->createChannelProduct('CP001', $salesChannel, $productSku);
        $cp->setStatus(ChannelProduct::STATUS_ACTIVE);
        $cp->setPlatformPrice('99.00');

        $gateway = $this->createRealMockGateway();
        $this->gatewayRegistry->method('has')->willReturn(true);
        $this->gatewayRegistry->method('get')->willReturn($gateway);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $this->expectException(ChannelApiException::class);

        $this->service->pushToChannel(
            $cp,
            ChannelProductSyncLog::OPERATION_PUSH_PRODUCT,
        );
    }

    // ========================================================================
    // Helper methods
    // ========================================================================

    private function createSalesChannel(string $code, ?array $config = null): SalesChannel
    {
        $channel = new SalesChannel();
        $channel->setCode($code);
        $channel->setName($code.' Channel');
        if ($config !== null) {
            $channel->setConfig($config);
        }

        return $channel;
    }

    private function createProductSkuWithProduct(): ProductSku
    {
        $brand = new Brand();
        $brand->setName('Test Brand');
        $brand->setSlug('test-brand');

        $category = new Category();
        $category->setName('Sneakers');
        $category->setSlug('sneakers');

        $product = new Product();
        $product->setId('PRD001');
        $product->setName('Test Sneaker');
        $product->setSlug('test-sneaker');
        $product->setStyleNumber('TS-001');
        $product->setSeason('2025SS');
        $product->setBrand($brand);
        $product->setCategory($category);
        $product->setDescription('A test sneaker');

        $sku = new ProductSku();
        $sku->setId('SKU001');
        $sku->setProduct($product);
        $sku->setSizeValue('42');
        $sku->setPrice('100.00');
        $sku->setCurrency('USD');
        $sku->setBarcode('1234567890123');

        return $sku;
    }

    private function createChannelProduct(string $id, SalesChannel $channel, ProductSku $sku): ChannelProduct
    {
        $cp = new ChannelProduct();
        $cp->setId($id);
        $cp->setSalesChannel($channel);
        $cp->setProductSku($sku);
        $cp->setPlatformPrice('0.00');
        $cp->setStatus(ChannelProduct::STATUS_DRAFT);

        return $cp;
    }

    private function createMockListing(
        string $id,
        string $status,
        string $price,
        SalesChannel $salesChannel,
        ProductSku $productSku,
        int $availableQuantity = 10,
    ): InventoryListing&MockObject {
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('M001');

        $merchantInventory = $this->createMock(MerchantInventory::class);
        $merchantInventory->method('getId')->willReturn('INV001');
        $merchantInventory->method('getMerchant')->willReturn($merchant);
        $merchantInventory->method('getProductSku')->willReturn($productSku);
        $merchantInventory->method('getShareableQuantity')->willReturn($availableQuantity);

        $listing = $this->createMock(InventoryListing::class);
        $listing->method('getId')->willReturn($id);
        $listing->method('getStatus')->willReturn($status);
        $listing->method('getPrice')->willReturn($price);
        $listing->method('getMerchantInventory')->willReturn($merchantInventory);
        $listing->method('getAvailableQuantity')->willReturn($availableQuantity);

        $merchantSalesChannel = $this->createMock(MerchantSalesChannel::class);
        $merchantSalesChannel->method('getSalesChannel')->willReturn($salesChannel);
        $listing->method('getMerchantSalesChannel')->willReturn($merchantSalesChannel);

        return $listing;
    }

    private function createRealMockGateway(): MockGateway
    {
        $gatewayLogger = $this->createMock(LoggerInterface::class);
        $mockOrderStore = $this->createMock(MockOrderStore::class);
        $channelProductRepo = $this->createMock(ChannelProductRepository::class);

        return new MockGateway($gatewayLogger, $mockOrderStore, $channelProductRepo);
    }
}
