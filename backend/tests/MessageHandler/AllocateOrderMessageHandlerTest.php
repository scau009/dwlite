<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Order;
use App\Message\AllocateOrderMessage;
use App\MessageHandler\AllocateOrderMessageHandler;
use App\Repository\OrderRepository;
use App\Service\Fulfillment\Dto\AllocationResult;
use App\Service\Fulfillment\FulfillmentAllocationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AllocateOrderMessageHandlerTest extends TestCase
{
    private OrderRepository&MockObject $orderRepository;
    private FulfillmentAllocationService&MockObject $allocationService;
    private LoggerInterface&MockObject $logger;
    private AllocateOrderMessageHandler $handler;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->allocationService = $this->createMock(FulfillmentAllocationService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new AllocateOrderMessageHandler(
            $this->orderRepository,
            $this->allocationService,
            $this->logger,
        );
    }

    public function testHandleSuccessfulAllocation(): void
    {
        $message = new AllocateOrderMessage('order-123', [], 1);

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');
        $order->method('canAllocate')->willReturn(true);
        $order->method('getStatus')->willReturn('pending');

        $this->orderRepository->expects($this->once())
            ->method('find')
            ->with('order-123')
            ->willReturn($order);

        $result = new AllocationResult(
            success: true,
            fulfillments: [],
            failureReason: null,
            attemptNumber: 1
        );

        $this->allocationService->expects($this->once())
            ->method('allocateOrder')
            ->with($order, [], 1)
            ->willReturn($result);

        $this->logger->expects($this->atLeastOnce())
            ->method('info');

        ($this->handler)($message);
    }

    public function testHandleOrderNotFound(): void
    {
        $message = new AllocateOrderMessage('order-999', [], 1);

        $this->orderRepository->expects($this->once())
            ->method('find')
            ->with('order-999')
            ->willReturn(null);

        $this->allocationService->expects($this->never())
            ->method('allocateOrder');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Order not found for allocation',
                $this->arrayHasKey('orderId')
            );

        ($this->handler)($message);
    }

    public function testHandleOrderNotAllocatable(): void
    {
        $message = new AllocateOrderMessage('order-123', [], 1);

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');
        $order->method('canAllocate')->willReturn(false);
        $order->method('getStatus')->willReturn('completed');

        $this->orderRepository->expects($this->once())
            ->method('find')
            ->willReturn($order);

        $this->allocationService->expects($this->never())
            ->method('allocateOrder');

        ($this->handler)($message);
    }

    public function testHandleAllocationFailure(): void
    {
        $message = new AllocateOrderMessage('order-123', [], 1);

        $order = $this->createMock(Order::class);
        $order->method('canAllocate')->willReturn(true);

        $this->orderRepository->expects($this->once())
            ->method('find')
            ->willReturn($order);

        $result = new AllocationResult(
            success: false,
            fulfillments: [],
            failureReason: 'Insufficient stock',
            attemptNumber: 1
        );

        $this->allocationService->expects($this->once())
            ->method('allocateOrder')
            ->willReturn($result);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Order allocation failed',
                $this->callback(function ($context) {
                    return isset($context['reason']) && $context['reason'] === 'Insufficient stock';
                })
            );

        ($this->handler)($message);
    }

    public function testHandleAllocationException(): void
    {
        $message = new AllocateOrderMessage('order-123', [], 1);

        $order = $this->createMock(Order::class);
        $order->method('canAllocate')->willReturn(true);

        $this->orderRepository->expects($this->once())
            ->method('find')
            ->willReturn($order);

        $exception = new \RuntimeException('Allocation failed');

        $this->allocationService->expects($this->once())
            ->method('allocateOrder')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Allocation failed');

        ($this->handler)($message);
    }

    public function testHandleWithExcludedMerchants(): void
    {
        $message = new AllocateOrderMessage(
            'order-123',
            ['merchant-1', 'merchant-2'],
            2
        );

        $order = $this->createMock(Order::class);
        $order->method('canAllocate')->willReturn(true);

        $this->orderRepository->expects($this->once())
            ->method('find')
            ->willReturn($order);

        $result = new AllocationResult(
            success: true,
            fulfillments: [],
            failureReason: null,
            attemptNumber: 2
        );

        $this->allocationService->expects($this->once())
            ->method('allocateOrder')
            ->with(
                $order,
                ['merchant-1', 'merchant-2'],
                2
            )
            ->willReturn($result);

        ($this->handler)($message);
    }
}
