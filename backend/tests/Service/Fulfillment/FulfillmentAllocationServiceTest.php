<?php

declare(strict_types=1);

namespace App\Tests\Service\Fulfillment;

use App\Entity\Fulfillment;
use App\Entity\Merchant;
use App\Entity\MerchantInventory;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\ProductSku;
use App\Entity\Warehouse;
use App\Repository\FulfillmentRepository;
use App\Repository\InventoryListingRepository;
use App\Repository\MerchantInventoryRepository;
use App\Repository\WarehouseRepository;
use App\Service\BusinessNoGenerator;
use App\Service\Fulfillment\Dto\AllocationResult;
use App\Service\Fulfillment\FulfillmentAllocationService;
use App\Service\InventoryService;
use App\Service\RuleEngine\RuleEngineService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FulfillmentAllocationServiceTest extends TestCase
{
    private FulfillmentRepository&MockObject $fulfillmentRepository;
    private WarehouseRepository&MockObject $warehouseRepository;
    private MerchantInventoryRepository&MockObject $inventoryRepository;
    private InventoryListingRepository&MockObject $listingRepository;
    private InventoryService&MockObject $inventoryService;
    private RuleEngineService&MockObject $ruleEngineService;
    private BusinessNoGenerator&MockObject $businessNoGenerator;
    private EntityManagerInterface&MockObject $entityManager;
    private LoggerInterface&MockObject $logger;
    private FulfillmentAllocationService $service;

    protected function setUp(): void
    {
        $this->fulfillmentRepository = $this->createMock(FulfillmentRepository::class);
        $this->warehouseRepository = $this->createMock(WarehouseRepository::class);
        $this->inventoryRepository = $this->createMock(MerchantInventoryRepository::class);
        $this->listingRepository = $this->createMock(InventoryListingRepository::class);
        $this->inventoryService = $this->createMock(InventoryService::class);
        $this->ruleEngineService = $this->createMock(RuleEngineService::class);
        $this->businessNoGenerator = $this->createMock(BusinessNoGenerator::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new FulfillmentAllocationService(
            $this->fulfillmentRepository,
            $this->warehouseRepository,
            $this->inventoryRepository,
            $this->listingRepository,
            $this->inventoryService,
            $this->ruleEngineService,
            $this->businessNoGenerator,
            $this->entityManager,
            $this->logger,
        );
    }

    public function testAllocateOrderToPlatformWarehouse(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $warehouse = $this->createMock(Warehouse::class);
        $warehouse->method('getId')->willReturn('warehouse-123');
        $warehouse->method('isPlatformWarehouse')->willReturn(true);

        $sku = $this->createMock(ProductSku::class);
        $sku->method('getId')->willReturn('sku-123');

        $orderItem = $this->createMock(OrderItem::class);
        $orderItem->method('getId')->willReturn('item-123');
        $orderItem->method('getProductSku')->willReturn($sku);
        $orderItem->method('getQuantity')->willReturn(1);

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');
        $order->method('getItems')->willReturn(new ArrayCollection([$orderItem]));
        $order->method('canAllocate')->willReturn(true);

        $inventory = $this->createMock(MerchantInventory::class);
        $inventory->method('getId')->willReturn('inventory-123');
        $inventory->method('getQuantityAvailable')->willReturn(10);
        $inventory->method('getMerchant')->willReturn($merchant);
        $inventory->method('getWarehouse')->willReturn($warehouse);

        $this->warehouseRepository->expects($this->once())
            ->method('findPlatformWarehouses')
            ->willReturn([$warehouse]);

        $this->inventoryRepository->expects($this->once())
            ->method('findAvailableForSku')
            ->willReturn([$inventory]);

        $this->businessNoGenerator->expects($this->once())
            ->method('generateFulfillmentNo')
            ->willReturn('FF20240115000001');

        $this->inventoryService->expects($this->once())
            ->method('reserveStock');

        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->allocateOrder($order, [], 1);

        $this->assertInstanceOf(AllocationResult::class, $result);
        $this->assertTrue($result->isSuccess());
    }

    public function testAllocateOrderWithInsufficientStock(): void
    {
        $sku = $this->createMock(ProductSku::class);
        $sku->method('getId')->willReturn('sku-123');

        $orderItem = $this->createMock(OrderItem::class);
        $orderItem->method('getProductSku')->willReturn($sku);
        $orderItem->method('getQuantity')->willReturn(10);

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');
        $order->method('getItems')->willReturn(new ArrayCollection([$orderItem]));
        $order->method('canAllocate')->willReturn(true);

        $this->warehouseRepository->expects($this->once())
            ->method('findPlatformWarehouses')
            ->willReturn([]);

        $this->listingRepository->expects($this->once())
            ->method('findActiveListingsForSku')
            ->willReturn([]);

        $result = $this->service->allocateOrder($order, [], 1);

        $this->assertInstanceOf(AllocationResult::class, $result);
        $this->assertFalse($result->isSuccess());
    }

    public function testAllocateOrderWithExcludedMerchants(): void
    {
        $excludedMerchant = $this->createMock(Merchant::class);
        $excludedMerchant->method('getId')->willReturn('merchant-excluded');

        $sku = $this->createMock(ProductSku::class);
        $sku->method('getId')->willReturn('sku-123');

        $orderItem = $this->createMock(OrderItem::class);
        $orderItem->method('getProductSku')->willReturn($sku);
        $orderItem->method('getQuantity')->willReturn(1);

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');
        $order->method('getItems')->willReturn(new ArrayCollection([$orderItem]));
        $order->method('canAllocate')->willReturn(true);

        $this->warehouseRepository->expects($this->once())
            ->method('findPlatformWarehouses')
            ->willReturn([]);

        // Should filter out excluded merchants
        $this->listingRepository->expects($this->once())
            ->method('findActiveListingsForSku')
            ->willReturn([]);

        $result = $this->service->allocateOrder($order, ['merchant-excluded'], 1);

        $this->assertFalse($result->isSuccess());
    }

    public function testAllocateOrderCreatesFulfillment(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $warehouse = $this->createMock(Warehouse::class);
        $warehouse->method('getId')->willReturn('warehouse-123');
        $warehouse->method('isPlatformWarehouse')->willReturn(true);

        $sku = $this->createMock(ProductSku::class);
        $orderItem = $this->createMock(OrderItem::class);
        $orderItem->method('getProductSku')->willReturn($sku);
        $orderItem->method('getQuantity')->willReturn(1);

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');
        $order->method('getItems')->willReturn(new ArrayCollection([$orderItem]));
        $order->method('canAllocate')->willReturn(true);

        $inventory = $this->createMock(MerchantInventory::class);
        $inventory->method('getQuantityAvailable')->willReturn(10);
        $inventory->method('getMerchant')->willReturn($merchant);
        $inventory->method('getWarehouse')->willReturn($warehouse);

        $this->warehouseRepository->method('findPlatformWarehouses')->willReturn([$warehouse]);
        $this->inventoryRepository->method('findAvailableForSku')->willReturn([$inventory]);
        $this->businessNoGenerator->method('generateFulfillmentNo')->willReturn('FF001');

        $persistedEntities = [];
        $this->entityManager->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedEntities) {
                $persistedEntities[] = $entity;
            });

        $result = $this->service->allocateOrder($order, [], 1);

        $this->assertTrue($result->isSuccess());
        $this->assertNotEmpty($result->getFulfillments());
    }
}
