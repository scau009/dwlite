<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\Listing\CreateListingRequest;
use App\Dto\Listing\UpdateListingRequest;
use App\Entity\InventoryListing;
use App\Entity\Merchant;
use App\Entity\MerchantInventory;
use App\Entity\SalesChannel;
use App\Entity\User;
use App\Repository\InventoryListingRepository;
use App\Repository\MerchantSalesChannelRepository;
use App\Repository\SalesChannelRepository;
use App\Service\ChannelProductSyncService;
use App\Service\InventoryListingService;
use App\Service\ListingOperationLogService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class InventoryListingServiceTest extends TestCase
{
    private InventoryListingRepository&MockObject $listingRepository;
    private SalesChannelRepository&MockObject $salesChannelRepository;
    private MerchantSalesChannelRepository&MockObject $merchantChannelRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private ChannelProductSyncService&MockObject $syncService;
    private ListingOperationLogService&MockObject $logService;
    private TranslatorInterface&MockObject $translator;
    private LoggerInterface&MockObject $logger;
    private InventoryListingService $service;

    protected function setUp(): void
    {
        $this->listingRepository = $this->createMock(InventoryListingRepository::class);
        $this->salesChannelRepository = $this->createMock(SalesChannelRepository::class);
        $this->merchantChannelRepository = $this->createMock(MerchantSalesChannelRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->syncService = $this->createMock(ChannelProductSyncService::class);
        $this->logService = $this->createMock(ListingOperationLogService::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->translator->method('trans')->willReturnCallback(fn(string $key) => $key);

        $this->service = new InventoryListingService(
            $this->listingRepository,
            $this->salesChannelRepository,
            $this->merchantChannelRepository,
            $this->entityManager,
            $this->syncService,
            $this->logService,
            $this->translator,
            $this->logger,
        );
    }

    public function testCreateListing(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $inventory = $this->createMock(MerchantInventory::class);
        $inventory->method('getId')->willReturn('inventory-123');
        $inventory->method('getMerchant')->willReturn($merchant);

        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getId')->willReturn('channel-123');

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-123');

        $dto = new CreateListingRequest();
        $dto->inventoryId = 'inventory-123';
        $dto->salesChannelId = 'channel-123';
        $dto->price = '100.00';
        $dto->fulfillmentMode = InventoryListing::FULFILLMENT_CONSIGNMENT;

        $this->salesChannelRepository->expects($this->once())
            ->method('find')
            ->with('channel-123')
            ->willReturn($channel);

        $this->listingRepository->expects($this->once())
            ->method('findByInventoryAndChannel')
            ->with($inventory, $channel)
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(InventoryListing::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->logService->expects($this->once())
            ->method('logCreate');

        $result = $this->service->createListing($merchant, $inventory, $dto, $user);

        $this->assertInstanceOf(InventoryListing::class, $result);
    }

    public function testCreateDuplicateListingFails(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $inventory = $this->createMock(MerchantInventory::class);
        $inventory->method('getMerchant')->willReturn($merchant);

        $channel = $this->createMock(SalesChannel::class);
        $existingListing = $this->createMock(InventoryListing::class);
        $user = $this->createMock(User::class);

        $dto = new CreateListingRequest();
        $dto->salesChannelId = 'channel-123';
        $dto->price = '100.00';
        $dto->fulfillmentMode = InventoryListing::FULFILLMENT_CONSIGNMENT;

        $this->salesChannelRepository->expects($this->once())
            ->method('find')
            ->willReturn($channel);

        $this->listingRepository->expects($this->once())
            ->method('findByInventoryAndChannel')
            ->willReturn($existingListing);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('listing.already_exists');

        $this->service->createListing($merchant, $inventory, $dto, $user);
    }

    public function testUpdateListing(): void
    {
        $listing = $this->createMock(InventoryListing::class);
        $listing->method('getId')->willReturn('listing-123');
        $listing->method('isActive')->willReturn(true);

        $user = $this->createMock(User::class);

        $dto = new UpdateListingRequest();
        $dto->price = '120.00';

        $listing->expects($this->once())
            ->method('setPrice')
            ->with('120.00');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->syncService->expects($this->once())
            ->method('triggerSyncFromListing');

        $this->logService->expects($this->once())
            ->method('logUpdate');

        $result = $this->service->updateListing($listing, $dto, $user);

        $this->assertSame($listing, $result);
    }

    public function testActivateListing(): void
    {
        $listing = $this->createMock(InventoryListing::class);
        $listing->method('getId')->willReturn('listing-123');
        $listing->method('getStatus')->willReturn(InventoryListing::STATUS_PAUSED);

        $listing->expects($this->once())
            ->method('setStatus')
            ->with(InventoryListing::STATUS_ACTIVE);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->syncService->expects($this->once())
            ->method('triggerSyncFromListing');

        $result = $this->service->activateListing($listing);

        $this->assertSame($listing, $result);
    }

    public function testPauseListing(): void
    {
        $listing = $this->createMock(InventoryListing::class);
        $listing->method('getId')->willReturn('listing-123');
        $listing->method('getStatus')->willReturn(InventoryListing::STATUS_ACTIVE);

        $listing->expects($this->once())
            ->method('setStatus')
            ->with(InventoryListing::STATUS_PAUSED);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->syncService->expects($this->once())
            ->method('triggerSyncFromListing');

        $result = $this->service->pauseListing($listing);

        $this->assertSame($listing, $result);
    }

    public function testDeleteListing(): void
    {
        $listing = $this->createMock(InventoryListing::class);
        $listing->method('getId')->willReturn('listing-123');

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($listing);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->syncService->expects($this->once())
            ->method('triggerSyncFromListing');

        $this->service->deleteListing($listing);
    }
}
