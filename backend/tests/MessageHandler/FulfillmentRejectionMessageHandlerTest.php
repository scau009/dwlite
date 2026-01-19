<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Fulfillment;
use App\Entity\Order;
use App\Message\AllocateOrderMessage;
use App\Message\FulfillmentRejectionMessage;
use App\MessageHandler\FulfillmentRejectionMessageHandler;
use App\Repository\FulfillmentRepository;
use App\Service\Fulfillment\FulfillmentAllocationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class FulfillmentRejectionMessageHandlerTest extends TestCase
{
    private FulfillmentRepository&MockObject $fulfillmentRepository;
    private FulfillmentAllocationService&MockObject $allocationService;
    private MessageBusInterface&MockObject $messageBus;
    private LoggerInterface&MockObject $logger;
    private FulfillmentRejectionMessageHandler $handler;

    protected function setUp(): void
    {
        $this->fulfillmentRepository = $this->createMock(FulfillmentRepository::class);
        $this->allocationService = $this->createMock(FulfillmentAllocationService::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new FulfillmentRejectionMessageHandler(
            $this->fulfillmentRepository,
            $this->allocationService,
            $this->messageBus,
            $this->logger,
        );
    }

    public function testHandleSuccessfulRejection(): void
    {
        $message = new FulfillmentRejectionMessage(
            'fulfillment-123',
            'Out of stock',
            false
        );

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getId')->willReturn('fulfillment-123');
        $fulfillment->method('getOrder')->willReturn($order);

        $this->fulfillmentRepository->expects($this->once())
            ->method('find')
            ->with('fulfillment-123')
            ->willReturn($fulfillment);

        $this->allocationService->expects($this->once())
            ->method('handleFulfillmentRejection')
            ->with($fulfillment, 'Out of stock', false);

        $this->allocationService->expects($this->once())
            ->method('getNextAllocationParams')
            ->with($fulfillment)
            ->willReturn([
                'excludedMerchantIds' => ['merchant-1'],
                'attemptNumber' => 2,
            ]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AllocateOrderMessage $msg) {
                return $msg->orderId === 'order-123'
                    && $msg->excludedMerchantIds === ['merchant-1']
                    && $msg->attemptNumber === 2;
            }))
            ->willReturn(new Envelope(new AllocateOrderMessage('order-123', ['merchant-1'], 2)));

        ($this->handler)($message);
    }

    public function testHandleTimeoutRejection(): void
    {
        $message = new FulfillmentRejectionMessage(
            'fulfillment-123',
            'Accept timeout',
            true
        );

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getOrder')->willReturn($order);

        $this->fulfillmentRepository->expects($this->once())
            ->method('find')
            ->willReturn($fulfillment);

        $this->allocationService->expects($this->once())
            ->method('handleFulfillmentRejection')
            ->with($fulfillment, 'Accept timeout', true);

        $this->allocationService->expects($this->once())
            ->method('getNextAllocationParams')
            ->willReturn([
                'excludedMerchantIds' => [],
                'attemptNumber' => 1,
            ]);

        $this->messageBus->expects($this->once())
            ->method('dispatch');

        ($this->handler)($message);
    }

    public function testHandleFulfillmentNotFound(): void
    {
        $message = new FulfillmentRejectionMessage(
            'fulfillment-999',
            'Out of stock',
            false
        );

        $this->fulfillmentRepository->expects($this->once())
            ->method('find')
            ->willReturn(null);

        $this->allocationService->expects($this->never())
            ->method('handleFulfillmentRejection');

        $this->messageBus->expects($this->never())
            ->method('dispatch');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Fulfillment not found',
                $this->arrayHasKey('fulfillmentId')
            );

        ($this->handler)($message);
    }

    public function testHandleException(): void
    {
        $message = new FulfillmentRejectionMessage(
            'fulfillment-123',
            'Out of stock',
            false
        );

        $fulfillment = $this->createMock(Fulfillment::class);

        $this->fulfillmentRepository->expects($this->once())
            ->method('find')
            ->willReturn($fulfillment);

        $exception = new \RuntimeException('Processing failed');

        $this->allocationService->expects($this->once())
            ->method('handleFulfillmentRejection')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Processing failed');

        ($this->handler)($message);
    }
}
