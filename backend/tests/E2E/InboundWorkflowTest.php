<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Entity\InboundOrder;
use App\Entity\InboundOrderItem;
use App\Entity\MerchantInventory;
use App\Entity\ProductSku;
use App\Entity\Warehouse;
use App\Repository\InboundOrderRepository;
use App\Repository\MerchantInventoryRepository;
use App\Service\InboundOrderService;
use App\Service\InventoryService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests the complete inbound workflow from order creation to inventory update.
 */
class InboundWorkflowTest extends TestCase
{
    private InboundOrderService&MockObject $inboundService;
    private InventoryService&MockObject $inventoryService;
    private InboundOrderRepository&MockObject $inboundOrderRepo;
    private MerchantInventoryRepository&MockObject $inventoryRepo;

    protected function setUp(): void
    {
        $this->inboundService = $this->createMock(InboundOrderService::class);
        $this->inventoryService = $this->createMock(InventoryService::class);
        $this->inboundOrderRepo = $this->createMock(InboundOrderRepository::class);
        $this->inventoryRepo = $this->createMock(MerchantInventoryRepository::class);
    }

    public function testCompleteInboundFlow(): void
    {
        // Step 1: Merchant creates inbound order
        $warehouse = $this->createMock(Warehouse::class);
        $sku = $this->createMock(ProductSku::class);
        $sku->method('getId')->willReturn('sku-123');

        $inboundOrder = $this->createMock(InboundOrder::class);
        $inboundOrder->method('getId')->willReturn('inbound-123');
        $inboundOrder->method('getInboundNo')->willReturn('IB20240115000001');
        $inboundOrder->method('getStatus')->willReturn(InboundOrder::STATUS_DRAFT);
        $inboundOrder->method('getWarehouse')->willReturn($warehouse);

        $item = $this->createMock(InboundOrderItem::class);
        $item->method('getProductSku')->willReturn($sku);
        $item->method('getPlannedQuantity')->willReturn(10);
        $item->method('getReceivedQuantity')->willReturn(0);
        $item->method('getActualQuantity')->willReturn(0);

        // Step 2: Order is shipped
        $inboundOrder->method('canShip')->willReturn(true);
        $inboundOrder->expects($this->once())->method('ship');

        $this->inboundService->expects($this->once())
            ->method('shipOrder')
            ->with($inboundOrder, $this->isType('string'))
            ->willReturn($inboundOrder);

        $shippedOrder = $this->inboundService->shipOrder($inboundOrder, 'TRACKING123');

        // Step 3: Warehouse receives and accepts the goods
        $shippedOrder->method('canReceive')->willReturn(true);
        $shippedOrder->expects($this->once())->method('receive');

        $item->expects($this->once())
            ->method('receive')
            ->with(10);

        // Step 4: Inventory is created or updated
        $inventory = $this->createMock(MerchantInventory::class);
        $inventory->method('getId')->willReturn('inventory-123');
        $inventory->method('getQuantityAvailable')->willReturn(10);

        $this->inventoryService->expects($this->once())
            ->method('addStock')
            ->with(
                $this->anything(),
                $sku,
                10,
                'inbound',
                'inbound-123'
            )
            ->willReturn($inventory);

        $resultInventory = $this->inventoryService->addStock(
            $warehouse,
            $sku,
            10,
            'inbound',
            'inbound-123'
        );

        $this->assertEquals(10, $resultInventory->getQuantityAvailable());
    }

    public function testPartialReceiptFlow(): void
    {
        $sku = $this->createMock(ProductSku::class);
        $warehouse = $this->createMock(Warehouse::class);

        $inboundOrder = $this->createMock(InboundOrder::class);
        $inboundOrder->method('getId')->willReturn('inbound-123');
        $inboundOrder->method('canReceive')->willReturn(true);

        $item = $this->createMock(InboundOrderItem::class);
        $item->method('getProductSku')->willReturn($sku);
        $item->method('getPlannedQuantity')->willReturn(10);

        // Receive only 8 units
        $item->expects($this->once())
            ->method('receive')
            ->with(8);

        // Inventory should be updated with actual received quantity
        $this->inventoryService->expects($this->once())
            ->method('addStock')
            ->with(
                $warehouse,
                $sku,
                8,
                'inbound',
                'inbound-123'
            );

        $this->inventoryService->addStock(
            $warehouse,
            $sku,
            8,
            'inbound',
            'inbound-123'
        );
    }

    public function testCancelInboundOrderFlow(): void
    {
        $inboundOrder = $this->createMock(InboundOrder::class);
        $inboundOrder->method('getId')->willReturn('inbound-123');
        $inboundOrder->method('getStatus')->willReturn(InboundOrder::STATUS_DRAFT);
        $inboundOrder->method('canCancel')->willReturn(true);

        // Order can be canceled before shipping
        $inboundOrder->expects($this->once())
            ->method('cancel')
            ->with('Merchant canceled');

        $this->inboundService->expects($this->once())
            ->method('cancelOrder')
            ->with($inboundOrder, 'Merchant canceled')
            ->willReturn($inboundOrder);

        $result = $this->inboundService->cancelOrder($inboundOrder, 'Merchant canceled');

        $this->assertEquals(InboundOrder::STATUS_DRAFT, $result->getStatus());
    }
}
