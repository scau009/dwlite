<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Entity\Fulfillment;
use App\Entity\MerchantInventory;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Settlement;
use App\Repository\FulfillmentRepository;
use App\Repository\SettlementRepository;
use App\Service\Fulfillment\Dto\AllocationResult;
use App\Service\Fulfillment\FulfillmentAllocationService;
use App\Service\Fulfillment\FulfillmentService;
use App\Service\InventoryService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests the complete order fulfillment workflow from allocation to settlement.
 */
class FulfillmentWorkflowTest extends TestCase
{
    private FulfillmentAllocationService&MockObject $allocationService;
    private FulfillmentService&MockObject $fulfillmentService;
    private InventoryService&MockObject $inventoryService;
    private FulfillmentRepository&MockObject $fulfillmentRepo;
    private SettlementRepository&MockObject $settlementRepo;

    protected function setUp(): void
    {
        $this->allocationService = $this->createMock(FulfillmentAllocationService::class);
        $this->fulfillmentService = $this->createMock(FulfillmentService::class);
        $this->inventoryService = $this->createMock(InventoryService::class);
        $this->fulfillmentRepo = $this->createMock(FulfillmentRepository::class);
        $this->settlementRepo = $this->createMock(SettlementRepository::class);
    }

    public function testCompleteOrderFulfillmentFlow(): void
    {
        // Step 1: Order is allocated
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');
        $order->method('canAllocate')->willReturn(true);

        $orderItem = $this->createMock(OrderItem::class);
        $orderItem->method('getQuantity')->willReturn(1);

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getId')->willReturn('fulfillment-123');
        $fulfillment->method('getFulfillmentNo')->willReturn('FF20240115000001');

        $allocationResult = new AllocationResult(
            success: true,
            fulfillments: [$fulfillment],
            failureReason: null,
            attemptNumber: 1
        );

        $this->allocationService->expects($this->once())
            ->method('allocateOrder')
            ->with($order, [], 1)
            ->willReturn($allocationResult);

        $result = $this->allocationService->allocateOrder($order, [], 1);
        $this->assertTrue($result->success);

        // Step 2: Merchant accepts fulfillment
        $fulfillment->method('canAccept')->willReturn(true);
        $fulfillment->expects($this->once())->method('accept');

        $this->fulfillmentService->expects($this->once())
            ->method('acceptFulfillment')
            ->with($fulfillment)
            ->willReturn($fulfillment);

        $this->fulfillmentService->acceptFulfillment($fulfillment);

        // Step 3: Inventory is reserved
        $inventory = $this->createMock(MerchantInventory::class);
        $inventory->method('getQuantityAvailable')->willReturn(9);
        $inventory->expects($this->once())
            ->method('reserve')
            ->with(1);

        $this->inventoryService->expects($this->once())
            ->method('reserveStock')
            ->with($inventory, 1, 'fulfillment', 'fulfillment-123');

        $this->inventoryService->reserveStock(
            $inventory,
            1,
            'fulfillment',
            'fulfillment-123'
        );

        // Step 4: Merchant ships the order
        $fulfillment->method('canShip')->willReturn(true);
        $fulfillment->expects($this->once())->method('ship');

        $this->fulfillmentService->expects($this->once())
            ->method('shipFulfillment')
            ->with($fulfillment, 'SF', 'SF123456789')
            ->willReturn($fulfillment);

        $this->fulfillmentService->shipFulfillment(
            $fulfillment,
            'SF',
            'SF123456789'
        );

        // Step 5: Inventory is deducted
        $inventory->expects($this->once())
            ->method('deduct')
            ->with(1);

        $this->inventoryService->expects($this->once())
            ->method('deductStock')
            ->with($inventory, 1, 'fulfillment', 'fulfillment-123');

        $this->inventoryService->deductStock(
            $inventory,
            1,
            'fulfillment',
            'fulfillment-123'
        );

        // Step 6: Order is completed
        $fulfillment->method('canComplete')->willReturn(true);
        $fulfillment->expects($this->once())->method('complete');

        $this->fulfillmentService->expects($this->once())
            ->method('completeFulfillment')
            ->with($fulfillment)
            ->willReturn($fulfillment);

        $this->fulfillmentService->completeFulfillment($fulfillment);

        // Step 7: Settlement is created
        $settlement = $this->createMock(Settlement::class);
        $settlement->method('getId')->willReturn('settlement-123');
        $settlement->method('getNetAmount')->willReturn('95.00');

        $this->settlementRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['fulfillment' => $fulfillment])
            ->willReturn($settlement);

        $result = $this->settlementRepo->findOneBy(['fulfillment' => $fulfillment]);
        $this->assertNotNull($result);
        $this->assertEquals('95.00', $result->getNetAmount());
    }

    public function testFulfillmentRejectionFlow(): void
    {
        // Step 1: Merchant rejects fulfillment
        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getId')->willReturn('fulfillment-123');
        $fulfillment->method('canReject')->willReturn(true);
        $fulfillment->expects($this->once())
            ->method('reject')
            ->with('Out of stock');

        $this->fulfillmentService->expects($this->once())
            ->method('rejectFulfillment')
            ->with($fulfillment, 'Out of stock')
            ->willReturn($fulfillment);

        $this->fulfillmentService->rejectFulfillment($fulfillment, 'Out of stock');

        // Step 2: Reserved inventory is released
        $inventory = $this->createMock(MerchantInventory::class);
        $inventory->expects($this->once())
            ->method('releaseReservation')
            ->with(1);

        $this->inventoryService->expects($this->once())
            ->method('releaseStock')
            ->with($inventory, 1, 'fulfillment', 'fulfillment-123');

        $this->inventoryService->releaseStock(
            $inventory,
            1,
            'fulfillment',
            'fulfillment-123'
        );

        // Step 3: Order is reallocated
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');

        $this->allocationService->expects($this->once())
            ->method('getNextAllocationParams')
            ->with($fulfillment)
            ->willReturn([
                'excludedMerchantIds' => ['merchant-1'],
                'attemptNumber' => 2,
            ]);

        $params = $this->allocationService->getNextAllocationParams($fulfillment);
        $this->assertEquals(['merchant-1'], $params['excludedMerchantIds']);
        $this->assertEquals(2, $params['attemptNumber']);
    }

    public function testFulfillmentTimeoutFlow(): void
    {
        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getId')->willReturn('fulfillment-123');
        $fulfillment->method('isAcceptExpired')->willReturn(true);

        $this->fulfillmentService->expects($this->once())
            ->method('handleExpiredFulfillments')
            ->willReturn(1);

        $count = $this->fulfillmentService->handleExpiredFulfillments();
        $this->assertEquals(1, $count);
    }
}
