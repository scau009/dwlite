<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\InboundOrder;
use App\Entity\InboundOrderItem;
use App\Entity\InventoryTransaction;
use App\Entity\Merchant;
use App\Entity\MerchantInventory;
use App\Entity\ProductSku;
use App\Entity\Warehouse;
use App\Repository\MerchantInventoryRepository;
use App\Repository\ProductSkuRepository;
use App\Service\ChannelProductSyncService;
use App\Service\InventoryService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class InventoryServiceTest extends TestCase
{
    private MerchantInventoryRepository&MockObject $inventoryRepository;
    private ProductSkuRepository&MockObject $skuRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private LoggerInterface&MockObject $logger;
    private ChannelProductSyncService&MockObject $channelProductSyncService;
    private InventoryService $service;

    protected function setUp(): void
    {
        $this->inventoryRepository = $this->createMock(MerchantInventoryRepository::class);
        $this->skuRepository = $this->createMock(ProductSkuRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->channelProductSyncService = $this->createMock(ChannelProductSyncService::class);

        $this->service = new InventoryService(
            $this->inventoryRepository,
            $this->skuRepository,
            $this->entityManager,
            $this->logger,
            $this->channelProductSyncService,
        );
    }

    public function testGetOrCreateInventoryExisting(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $warehouse = $this->createMock(Warehouse::class);
        $sku = $this->createMock(ProductSku::class);

        $existingInventory = $this->createMock(MerchantInventory::class);

        $this->inventoryRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'merchant' => $merchant,
                'warehouse' => $warehouse,
                'productSku' => $sku,
            ])
            ->willReturn($existingInventory);

        $result = $this->service->getOrCreateInventory($merchant, $warehouse, $sku);

        $this->assertSame($existingInventory, $result);
    }

    public function testGetOrCreateInventoryNew(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $warehouse = $this->createMock(Warehouse::class);
        $warehouse->method('getId')->willReturn('warehouse-123');

        $sku = $this->createMock(ProductSku::class);
        $sku->method('getId')->willReturn('sku-123');
        $sku->method('getCurrency')->willReturn('USD');

        $this->inventoryRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(MerchantInventory::class));

        $result = $this->service->getOrCreateInventory($merchant, $warehouse, $sku);

        $this->assertInstanceOf(MerchantInventory::class, $result);
    }

    public function testAddInTransitStock(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $warehouse = $this->createMock(Warehouse::class);
        $sku = $this->createMock(ProductSku::class);
        $sku->method('getCurrency')->willReturn('USD');

        $item = $this->createMock(InboundOrderItem::class);
        $item->method('getProductSku')->willReturn($sku);
        $item->method('getExpectedQuantity')->willReturn(10);
        $item->method('getUnitCost')->willReturn('50.00');
        $item->method('getStyleNumber')->willReturn('STYLE001');
        $item->method('getSkuName')->willReturn('US 10');

        $order = $this->createMock(InboundOrder::class);
        $order->method('getId')->willReturn('order-123');
        $order->method('getOrderNo')->willReturn('IB20240115000001');
        $order->method('getMerchant')->willReturn($merchant);
        $order->method('getWarehouse')->willReturn($warehouse);
        $order->method('getItems')->willReturn(new ArrayCollection([$item]));

        $inventory = $this->createMock(MerchantInventory::class);
        $inventory->method('getQuantityInTransit')->willReturn(0);

        $this->inventoryRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($inventory);

        $inventory->expects($this->once())
            ->method('addInTransit')
            ->with(10);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(InventoryTransaction::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->service->addInTransitStock($order, 'operator-123', 'Operator');
    }

    public function testReserveStock(): void
    {
        $inventory = $this->createMock(MerchantInventory::class);
        $inventory->method('getQuantityAvailable')->willReturn(100);
        $inventory->method('getQuantityReserved')->willReturn(0);

        $inventory->expects($this->once())
            ->method('reserve')
            ->with(10);

        $this->entityManager->expects($this->exactly(2))
            ->method('persist')
            ->with($this->isInstanceOf(InventoryTransaction::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->service->reserveStock(
            $inventory,
            10,
            'order',
            'order-123',
            'ORD20240115000001',
            'operator-123',
            'Operator'
        );
    }

    public function testReleaseStock(): void
    {
        $inventory = $this->createMock(MerchantInventory::class);
        $inventory->method('getQuantityReserved')->willReturn(10);
        $inventory->method('getQuantityAvailable')->willReturn(100);

        $inventory->expects($this->once())
            ->method('release')
            ->with(10);

        $this->channelProductSyncService->expects($this->once())
            ->method('triggerSyncFromInventory');

        $this->entityManager->expects($this->exactly(2))
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->service->releaseStock(
            $inventory,
            10,
            'order',
            'order-123',
            'ORD20240115000001',
            'operator-123',
            'Operator'
        );
    }

    public function testConfirmOutbound(): void
    {
        $inventory = $this->createMock(MerchantInventory::class);
        $inventory->method('getQuantityReserved')->willReturn(10);

        $inventory->expects($this->once())
            ->method('confirmOutbound')
            ->with(5);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(InventoryTransaction::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->service->confirmOutbound(
            $inventory,
            5,
            'fulfillment',
            'fulfillment-123',
            'FF20240115000001',
            'operator-123',
            'Operator'
        );
    }

    public function testInitializeInventory(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $warehouse = $this->createMock(Warehouse::class);
        $warehouse->method('getId')->willReturn('warehouse-123');
        $warehouse->method('isMerchantWarehouse')->willReturn(true);
        $warehouse->method('getMerchant')->willReturn($merchant);

        $sku = $this->createMock(ProductSku::class);
        $sku->method('getId')->willReturn('sku-123');

        $this->inventoryRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager->expects($this->exactly(2))
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->initializeInventory(
            $merchant,
            $warehouse,
            $sku,
            100,
            '50.00',
            'USD',
            'Initial import',
            'operator-123',
            'Operator'
        );

        $this->assertInstanceOf(MerchantInventory::class, $result);
    }

    public function testInitializeInventoryOnNonMerchantWarehouseFails(): void
    {
        $merchant = $this->createMock(Merchant::class);

        $warehouse = $this->createMock(Warehouse::class);
        $warehouse->method('isMerchantWarehouse')->willReturn(false);

        $sku = $this->createMock(ProductSku::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only merchant warehouses can use initializeInventory');

        $this->service->initializeInventory($merchant, $warehouse, $sku, 100);
    }

    public function testInitializeInventoryAlreadyExistsFails(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $warehouse = $this->createMock(Warehouse::class);
        $warehouse->method('isMerchantWarehouse')->willReturn(true);
        $warehouse->method('getMerchant')->willReturn($merchant);

        $sku = $this->createMock(ProductSku::class);

        $existingInventory = $this->createMock(MerchantInventory::class);
        $this->inventoryRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existingInventory);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Inventory already exists for this SKU in this warehouse');

        $this->service->initializeInventory($merchant, $warehouse, $sku, 100);
    }

    public function testAdjustInventoryIncrease(): void
    {
        $inventory = $this->createMock(MerchantInventory::class);
        $inventory->method('isMerchantOwned')->willReturn(true);
        $inventory->method('getQuantityAvailable')->willReturn(100);

        $inventory->expects($this->once())
            ->method('setQuantityAvailable')
            ->with(150);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(InventoryTransaction::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->channelProductSyncService->expects($this->once())
            ->method('triggerSyncFromInventory');

        $result = $this->service->adjustInventory(
            $inventory,
            'increase',
            50,
            null,
            'Test increase',
            'operator-123',
            'Operator'
        );

        $this->assertSame($inventory, $result);
    }

    public function testAdjustInventoryDecrease(): void
    {
        $inventory = $this->createMock(MerchantInventory::class);
        $inventory->method('isMerchantOwned')->willReturn(true);
        $inventory->method('getQuantityAvailable')->willReturn(100);

        $inventory->expects($this->once())
            ->method('setQuantityAvailable')
            ->with(70);

        $this->service->adjustInventory($inventory, 'decrease', 30);
    }

    public function testAdjustInventorySet(): void
    {
        $inventory = $this->createMock(MerchantInventory::class);
        $inventory->method('isMerchantOwned')->willReturn(true);
        $inventory->method('getQuantityAvailable')->willReturn(100);

        $inventory->expects($this->once())
            ->method('setQuantityAvailable')
            ->with(50);

        $this->service->adjustInventory($inventory, 'set', 50);
    }

    public function testAdjustInventoryOnNonMerchantOwnedFails(): void
    {
        $inventory = $this->createMock(MerchantInventory::class);
        $inventory->method('isMerchantOwned')->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('adjustInventory can only be used for merchant-owned inventory');

        $this->service->adjustInventory($inventory, 'increase', 50);
    }

    public function testAdjustInventoryDecreaseMoreThanAvailableFails(): void
    {
        $inventory = $this->createMock(MerchantInventory::class);
        $inventory->method('isMerchantOwned')->willReturn(true);
        $inventory->method('getQuantityAvailable')->willReturn(30);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot decrease more than available');

        $this->service->adjustInventory($inventory, 'decrease', 50);
    }

    public function testAdjustInventoryInvalidType(): void
    {
        $inventory = $this->createMock(MerchantInventory::class);
        $inventory->method('isMerchantOwned')->willReturn(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid adjustment type');

        $this->service->adjustInventory($inventory, 'invalid', 50);
    }
}
