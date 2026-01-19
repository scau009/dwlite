<?php

declare(strict_types=1);

namespace App\Tests\Service\OrderSync;

use App\Entity\Order;
use App\Entity\OrderException;
use App\Repository\OrderExceptionRepository;
use App\Service\OrderSync\OrderValidationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderValidationServiceTest extends TestCase
{
    private OrderExceptionRepository&MockObject $exceptionRepository;
    private OrderValidationService $service;

    protected function setUp(): void
    {
        $this->exceptionRepository = $this->createMock(OrderExceptionRepository::class);
        $this->service = new OrderValidationService($this->exceptionRepository);
    }

    public function testCanConfirmWithNoExceptions(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');

        $this->exceptionRepository->expects($this->once())
            ->method('countPendingByOrder')
            ->with($order)
            ->willReturn(0);

        $result = $this->service->canConfirm($order);

        $this->assertTrue($result);
    }

    public function testCannotConfirmWithPendingExceptions(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');

        $this->exceptionRepository->expects($this->once())
            ->method('countPendingByOrder')
            ->with($order)
            ->willReturn(2);

        $result = $this->service->canConfirm($order);

        $this->assertFalse($result);
    }

    public function testCountPendingExceptions(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');

        $this->exceptionRepository->expects($this->once())
            ->method('countPendingByOrder')
            ->with($order)
            ->willReturn(3);

        $result = $this->service->countPendingExceptions($order);

        $this->assertEquals(3, $result);
    }
}
