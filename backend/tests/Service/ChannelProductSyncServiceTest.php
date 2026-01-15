<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\ChannelProduct;
use App\Entity\ChannelProductSyncLog;
use App\Entity\InventoryListing;
use App\Entity\MerchantInventory;
use App\Entity\ProductSku;
use App\Entity\SalesChannel;
use App\Enum\SyncTriggerSource;
use App\Message\PushChannelProductMessage;
use App\Message\SyncChannelProductMessage;
use App\Repository\ChannelProductRepository;
use App\Repository\ChannelProductSyncLogRepository;
use App\Repository\InventoryListingRepository;
use App\Service\ChannelGateway\ChannelGatewayRegistry;
use App\Service\ChannelProductSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ChannelProductSyncServiceTest extends TestCase
{
    private ChannelProductRepository&MockObject $channelProductRepository;
    private ChannelProductSyncLogRepository&MockObject $syncLogRepository;
    private InventoryListingRepository&MockObject $listingRepository;
    private ChannelGatewayRegistry&MockObject $gatewayRegistry;
    private EntityManagerInterface&MockObject $entityManager;
    private MessageBusInterface&MockObject $messageBus;
    private CacheItemPoolInterface&MockObject $cache;
    private LoggerInterface&MockObject $logger;
    private ChannelProductSyncService $service;

    protected function setUp(): void
    {
        $this->channelProductRepository = $this->createMock(ChannelProductRepository::class);
        $this->syncLogRepository = $this->createMock(ChannelProductSyncLogRepository::class);
        $this->listingRepository = $this->createMock(InventoryListingRepository::class);
        $this->gatewayRegistry = $this->createMock(ChannelGatewayRegistry::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new ChannelProductSyncService(
            $this->channelProductRepository,
            $this->syncLogRepository,
            $this->listingRepository,
            $this->gatewayRegistry,
            $this->entityManager,
            $this->messageBus,
            $this->cache,
            $this->logger,
        );
    }

    public function testTriggerSyncFromListing(): void
    {
        $sku = $this->createMock(ProductSku::class);
        $sku->method('getId')->willReturn('sku-123');

        $inventory = $this->createMock(MerchantInventory::class);
        $inventory->method('getProductSku')->willReturn($sku);

        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getId')->willReturn('channel-123');

        $listing = $this->createMock(InventoryListing::class);
        $listing->method('getId')->willReturn('listing-123');
        $listing->method('getMerchantInventory')->willReturn($inventory);
        $listing->method('getSalesChannel')->willReturn($channel);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem);

        $cacheItem->expects($this->once())
            ->method('set')
            ->willReturnSelf();

        $cacheItem->expects($this->once())
            ->method('expiresAfter')
            ->with(5)
            ->willReturnSelf();

        $this->cache->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SyncChannelProductMessage::class))
            ->willReturn(new Envelope(new SyncChannelProductMessage('sku-123', 'channel-123', SyncTriggerSource::LISTING_CHANGE)));

        $this->service->triggerSyncFromListing($listing, SyncTriggerSource::LISTING_CHANGE);
    }

    public function testTriggerSyncFromInventory(): void
    {
        $sku = $this->createMock(ProductSku::class);
        $sku->method('getId')->willReturn('sku-123');

        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getId')->willReturn('channel-123');

        $listing = $this->createMock(InventoryListing::class);
        $listing->method('getSalesChannel')->willReturn($channel);

        $inventory = $this->createMock(MerchantInventory::class);
        $inventory->method('getId')->willReturn('inventory-123');
        $inventory->method('getProductSku')->willReturn($sku);

        $this->listingRepository->expects($this->once())
            ->method('findByInventory')
            ->with($inventory)
            ->willReturn([$listing]);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);

        $this->cache->method('getItem')->willReturn($cacheItem);
        $cacheItem->method('set')->willReturnSelf();
        $cacheItem->method('expiresAfter')->willReturnSelf();

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new SyncChannelProductMessage('sku-123', 'channel-123', SyncTriggerSource::INVENTORY_CHANGE)));

        $this->service->triggerSyncFromInventory($inventory, SyncTriggerSource::INVENTORY_CHANGE);
    }

    public function testDebouncing(): void
    {
        $sku = $this->createMock(ProductSku::class);
        $sku->method('getId')->willReturn('sku-123');

        $inventory = $this->createMock(MerchantInventory::class);
        $inventory->method('getProductSku')->willReturn($sku);

        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getId')->willReturn('channel-123');

        $listing = $this->createMock(InventoryListing::class);
        $listing->method('getMerchantInventory')->willReturn($inventory);
        $listing->method('getSalesChannel')->willReturn($channel);

        // Cache hit means debounce is active
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem);

        // Should not dispatch when debounced
        $this->messageBus->expects($this->never())
            ->method('dispatch');

        $this->service->triggerSyncFromListing($listing, SyncTriggerSource::LISTING_CHANGE);
    }
}
