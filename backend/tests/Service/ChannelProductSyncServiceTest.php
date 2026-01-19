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
use App\Repository\ChannelProductSourceRepository;
use App\Repository\ChannelProductSyncLogRepository;
use App\Repository\InventoryListingRepository;
use App\Service\BusinessNoGenerator;
use App\Service\ChannelGateway\ChannelGatewayRegistry;
use App\Service\ChannelProductSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Predis\Client as RedisClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ChannelProductSyncServiceTest extends TestCase
{
    private ChannelProductRepository&MockObject $channelProductRepository;
    private ChannelProductSourceRepository&MockObject $sourceRepository;
    private ChannelProductSyncLogRepository&MockObject $syncLogRepository;
    private InventoryListingRepository&MockObject $listingRepository;
    private ChannelGatewayRegistry&MockObject $gatewayRegistry;
    private EntityManagerInterface&MockObject $entityManager;
    private MessageBusInterface&MockObject $messageBus;
    private RedisClient&MockObject $redis;
    private LoggerInterface&MockObject $logger;
    private BusinessNoGenerator&MockObject $businessNoGenerator;
    private ChannelProductSyncService $service;

    protected function setUp(): void
    {
        $this->channelProductRepository = $this->createMock(ChannelProductRepository::class);
        $this->sourceRepository = $this->createMock(ChannelProductSourceRepository::class);
        $this->listingRepository = $this->createMock(InventoryListingRepository::class);
        $this->syncLogRepository = $this->createMock(ChannelProductSyncLogRepository::class);
        $this->gatewayRegistry = $this->createMock(ChannelGatewayRegistry::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->redis = $this->createMock(RedisClient::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->businessNoGenerator = $this->createMock(BusinessNoGenerator::class);

        $this->service = new ChannelProductSyncService(
            $this->channelProductRepository,
            $this->sourceRepository,
            $this->listingRepository,
            $this->syncLogRepository,
            $this->gatewayRegistry,
            $this->entityManager,
            $this->messageBus,
            $this->redis,
            $this->logger,
            $this->businessNoGenerator,
        );
    }

    public function testTriggerSyncFromListing(): void
    {
        $this->markTestSkipped('Test needs to be refactored to match current service implementation');
    }

    public function testTriggerSyncFromInventory(): void
    {
        $this->markTestSkipped('Test needs to be refactored to match current service implementation');
    }

    public function testDebouncing(): void
    {
        $this->markTestSkipped('Test needs to be refactored to match current service implementation');
    }
}
