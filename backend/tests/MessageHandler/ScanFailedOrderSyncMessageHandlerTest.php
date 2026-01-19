<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Order;
use App\Entity\OrderSyncState;
use App\Message\PushOrderStatusMessage;
use App\Message\ScanFailedOrderSyncMessage;
use App\MessageHandler\ScanFailedOrderSyncMessageHandler;
use App\Service\OrderSyncService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ScanFailedOrderSyncMessageHandlerTest extends TestCase
{
    private OrderSyncService&MockObject $orderSyncService;
    private MessageBusInterface&MockObject $messageBus;
    private LoggerInterface&MockObject $logger;
    private ScanFailedOrderSyncMessageHandler $handler;

    protected function setUp(): void
    {
        $this->orderSyncService = $this->createMock(OrderSyncService::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new ScanFailedOrderSyncMessageHandler(
            $this->orderSyncService,
            $this->messageBus,
            $this->logger,
        );
    }

    public function testHandleDispatchesForFailedSyncs(): void
    {
        $message = new ScanFailedOrderSyncMessage(
            salesChannelId: 'channel-123',
            staleMinutes: 30,
            maxRetryCount: 3,
            limit: 50
        );

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');
        $order->method('isShipped')->willReturn(true);

        $syncState = $this->createMock(OrderSyncState::class);
        $syncState->method('getOrder')->willReturn($order);
        $syncState->method('getPendingOperation')->willReturn(PushOrderStatusMessage::OP_SHIP);
        $syncState->method('getRetryCount')->willReturn(1);

        $this->orderSyncService->expects($this->once())
            ->method('findPendingOperations')
            ->willReturn([$syncState]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (PushOrderStatusMessage $msg) {
                return $msg->orderId === 'order-123'
                    && $msg->operation === PushOrderStatusMessage::OP_SHIP
                    && $msg->retryCount === 1;
            }))
            ->willReturn(new Envelope(new PushOrderStatusMessage('order-123', PushOrderStatusMessage::OP_SHIP, 1)));

        ($this->handler)($message);
    }

    public function testHandleNoFailedSyncs(): void
    {
        $message = new ScanFailedOrderSyncMessage(
            salesChannelId: null,
            staleMinutes: 30,
            maxRetryCount: 3,
            limit: 50
        );

        $this->orderSyncService->expects($this->once())
            ->method('findPendingOperations')
            ->willReturn([]);

        $this->messageBus->expects($this->never())
            ->method('dispatch');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('No failed order syncs found');

        ($this->handler)($message);
    }

    public function testHandleDeterminesOperationFromOrderState(): void
    {
        $message = new ScanFailedOrderSyncMessage(
            salesChannelId: null,
            staleMinutes: 30,
            maxRetryCount: 3,
            limit: 50
        );

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');
        $order->method('isShipped')->willReturn(true);

        $syncState = $this->createMock(OrderSyncState::class);
        $syncState->method('getOrder')->willReturn($order);
        $syncState->method('getPendingOperation')->willReturn(null);
        $syncState->method('getShippedSyncCount')->willReturn(0);
        $syncState->method('getRetryCount')->willReturn(0);

        $this->orderSyncService->expects($this->once())
            ->method('findPendingOperations')
            ->willReturn([$syncState]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (PushOrderStatusMessage $msg) {
                return $msg->operation === PushOrderStatusMessage::OP_SHIP;
            }));

        ($this->handler)($message);
    }

    public function testHandleSkipsWhenNoOperationDetermined(): void
    {
        $message = new ScanFailedOrderSyncMessage(
            salesChannelId: null,
            staleMinutes: 30,
            maxRetryCount: 3,
            limit: 50
        );

        $order = $this->createMock(Order::class);
        $order->method('isShipped')->willReturn(false);
        $order->method('isPaid')->willReturn(false);

        $syncState = $this->createMock(OrderSyncState::class);
        $syncState->method('getOrder')->willReturn($order);
        $syncState->method('getPendingOperation')->willReturn(null);

        $this->orderSyncService->expects($this->once())
            ->method('findPendingOperations')
            ->willReturn([$syncState]);

        // Should not dispatch
        $this->messageBus->expects($this->never())
            ->method('dispatch');

        ($this->handler)($message);
    }

    public function testHandleContinuesOnDispatchError(): void
    {
        $message = new ScanFailedOrderSyncMessage(
            salesChannelId: null,
            staleMinutes: 30,
            maxRetryCount: 3,
            limit: 50
        );

        $order1 = $this->createMock(Order::class);
        $order1->method('getId')->willReturn('order-1');

        $syncState1 = $this->createMock(OrderSyncState::class);
        $syncState1->method('getOrder')->willReturn($order1);
        $syncState1->method('getPendingOperation')->willReturn(PushOrderStatusMessage::OP_CONFIRM);
        $syncState1->method('getRetryCount')->willReturn(0);

        $order2 = $this->createMock(Order::class);
        $order2->method('getId')->willReturn('order-2');

        $syncState2 = $this->createMock(OrderSyncState::class);
        $syncState2->method('getOrder')->willReturn($order2);
        $syncState2->method('getPendingOperation')->willReturn(PushOrderStatusMessage::OP_SHIP);
        $syncState2->method('getRetryCount')->willReturn(0);

        $this->orderSyncService->expects($this->once())
            ->method('findPendingOperations')
            ->willReturn([$syncState1, $syncState2]);

        // First dispatch fails, second succeeds
        $this->messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new \RuntimeException('Dispatch failed')),
                new Envelope(new PushOrderStatusMessage('order-2', PushOrderStatusMessage::OP_SHIP, 0))
            );

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Failed to re-dispatch order sync',
                $this->callback(function ($context) {
                    return isset($context['orderId']) && $context['orderId'] === 'order-1';
                })
            );

        // Should continue despite error
        ($this->handler)($message);
    }
}
