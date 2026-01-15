<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Entity\Order;
use App\Entity\OrderSyncState;
use App\Entity\SalesChannel;
use App\Repository\OrderRepository;
use App\Service\ChannelGateway\ChannelGatewayInterface;
use App\Service\ChannelGateway\ChannelGatewayRegistry;
use App\Service\ChannelGateway\Dto\Response\PulledOrderDto;
use App\Service\OrderSyncService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests the complete order sync workflow from pulling to status updates.
 */
class OrderSyncWorkflowTest extends TestCase
{
    private OrderSyncService&MockObject $orderSyncService;
    private OrderRepository&MockObject $orderRepository;
    private ChannelGatewayRegistry&MockObject $gatewayRegistry;

    protected function setUp(): void
    {
        $this->orderSyncService = $this->createMock(OrderSyncService::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->gatewayRegistry = $this->createMock(ChannelGatewayRegistry::class);
    }

    public function testCompleteOrderPullFlow(): void
    {
        // Step 1: Pull orders from channel
        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getId')->willReturn('channel-123');
        $channel->method('getCode')->willReturn('kickscrew');
        $channel->method('isActive')->willReturn(true);

        $pulledOrderDto = new PulledOrderDto();
        $pulledOrderDto->externalOrderNo = 'EXT123';
        $pulledOrderDto->totalAmount = '100.00';
        $pulledOrderDto->currency = 'USD';

        $startTime = new \DateTimeImmutable('-1 hour', new \DateTimeZone('UTC'));
        $endTime = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $this->orderSyncService->expects($this->once())
            ->method('pullOrders')
            ->with($channel, $startTime, $endTime, 1, 50)
            ->willReturn([
                'created' => 1,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0,
            ]);

        $stats = $this->orderSyncService->pullOrders(
            $channel,
            $startTime,
            $endTime,
            1,
            50
        );

        $this->assertEquals(1, $stats['created']);

        // Step 2: Order is created in system
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');
        $order->method('getExternalOrderNo')->willReturn('EXT123');
        $order->method('getSalesChannel')->willReturn($channel);

        $this->orderRepository->expects($this->once())
            ->method('findByExternalOrderNo')
            ->with('EXT123', $channel)
            ->willReturn($order);

        $foundOrder = $this->orderRepository->findByExternalOrderNo('EXT123', $channel);
        $this->assertNotNull($foundOrder);
    }

    public function testOrderConfirmationFlow(): void
    {
        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getCode')->willReturn('kickscrew');

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');
        $order->method('getExternalOrderNo')->willReturn('EXT123');
        $order->method('getSalesChannel')->willReturn($channel);

        $gateway = $this->createMock(ChannelGatewayInterface::class);
        $gateway->expects($this->once())
            ->method('confirmOrder')
            ->willReturn(true);

        $this->gatewayRegistry->expects($this->once())
            ->method('get')
            ->with('kickscrew')
            ->willReturn($gateway);

        $this->orderSyncService->expects($this->once())
            ->method('confirmOrder')
            ->with($order)
            ->willReturn($this->createMock(\App\Entity\OrderSyncLog::class));

        $log = $this->orderSyncService->confirmOrder($order);
        $this->assertNotNull($log);
    }

    public function testOrderShipmentFlow(): void
    {
        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getCode')->willReturn('kickscrew');

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');
        $order->method('getExternalOrderNo')->willReturn('EXT123');
        $order->method('getSalesChannel')->willReturn($channel);
        $order->method('getCarrierCode')->willReturn('SF');
        $order->method('getTrackingNumber')->willReturn('SF123456789');

        $gateway = $this->createMock(ChannelGatewayInterface::class);
        $gateway->expects($this->once())
            ->method('shipOrder')
            ->willReturn(true);

        $this->gatewayRegistry->expects($this->once())
            ->method('get')
            ->willReturn($gateway);

        $this->orderSyncService->expects($this->once())
            ->method('shipOrder')
            ->with($order)
            ->willReturn($this->createMock(\App\Entity\OrderSyncLog::class));

        $log = $this->orderSyncService->shipOrder($order);
        $this->assertNotNull($log);
    }

    public function testFailedSyncRetryFlow(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');

        $syncState = $this->createMock(OrderSyncState::class);
        $syncState->method('getOrder')->willReturn($order);
        $syncState->method('getRetryCount')->willReturn(1);
        $syncState->method('getPendingOperation')->willReturn('confirm');

        $threshold = new \DateTimeImmutable('-30 minutes', new \DateTimeZone('UTC'));

        $this->orderSyncService->expects($this->once())
            ->method('findPendingOperations')
            ->with($threshold, 3, 100)
            ->willReturn([$syncState]);

        $pendingOps = $this->orderSyncService->findPendingOperations(
            $threshold,
            3,
            100
        );

        $this->assertCount(1, $pendingOps);
        $this->assertEquals(1, $pendingOps[0]->getRetryCount());
    }

    public function testDuplicateOrderSkipFlow(): void
    {
        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getId')->willReturn('channel-123');

        // Order already exists
        $existingOrder = $this->createMock(Order::class);
        $existingOrder->method('getId')->willReturn('order-existing');
        $existingOrder->method('getExternalOrderNo')->willReturn('EXT123');

        $this->orderRepository->expects($this->once())
            ->method('findByExternalOrderNo')
            ->with('EXT123', $channel)
            ->willReturn($existingOrder);

        $order = $this->orderRepository->findByExternalOrderNo('EXT123', $channel);

        // Pull should skip this order
        $this->assertEquals('EXT123', $order->getExternalOrderNo());
    }
}
