<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\ChannelProduct;
use App\Message\PushChannelProductMessage;
use App\Message\ScanPendingSyncMessage;
use App\MessageHandler\ScanPendingSyncMessageHandler;
use App\Repository\ChannelProductRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ScanPendingSyncMessageHandlerTest extends TestCase
{
    private ChannelProductRepository&MockObject $channelProductRepo;
    private MessageBusInterface&MockObject $messageBus;
    private LoggerInterface&MockObject $logger;
    private ScanPendingSyncMessageHandler $handler;

    protected function setUp(): void
    {
        $this->channelProductRepo = $this->createMock(ChannelProductRepository::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new ScanPendingSyncMessageHandler(
            $this->channelProductRepo,
            $this->messageBus,
            $this->logger,
        );
    }

    public function testHandleDispatchesForStaleProducts(): void
    {
        $threshold = new \DateTimeImmutable('-5 minutes', new \DateTimeZone('UTC'));
        $message = new ScanPendingSyncMessage(
            salesChannelId: 'channel-123',
            staleMinutes: 5,
            limit: 100
        );

        // Product with external ID should update
        $product1 = $this->createMock(ChannelProduct::class);
        $product1->method('getId')->willReturn('product-1');
        $product1->method('getExternalId')->willReturn('ext-123');

        // Product without external ID should push
        $product2 = $this->createMock(ChannelProduct::class);
        $product2->method('getId')->willReturn('product-2');
        $product2->method('getExternalId')->willReturn(null);

        $this->channelProductRepo->expects($this->once())
            ->method('findStalePending')
            ->with(
                $this->isInstanceOf(\DateTimeImmutable::class),
                'channel-123',
                100
            )
            ->willReturn([$product1, $product2]);

        $this->messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [$this->callback(function (PushChannelProductMessage $msg) {
                    return $msg->channelProductId === 'product-1'
                        && $msg->operation === PushChannelProductMessage::OPERATION_UPDATE_STOCK_PRICE;
                })],
                [$this->callback(function (PushChannelProductMessage $msg) {
                    return $msg->channelProductId === 'product-2'
                        && $msg->operation === PushChannelProductMessage::OPERATION_PUSH_PRODUCT;
                })]
            )
            ->willReturn(new Envelope(new PushChannelProductMessage('product-1', PushChannelProductMessage::OPERATION_UPDATE_STOCK_PRICE)));

        ($this->handler)($message);
    }

    public function testHandleNoStaleProducts(): void
    {
        $message = new ScanPendingSyncMessage(
            salesChannelId: null,
            staleMinutes: 5,
            limit: 100
        );

        $this->channelProductRepo->expects($this->once())
            ->method('findStalePending')
            ->willReturn([]);

        $this->messageBus->expects($this->never())
            ->method('dispatch');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('No stale pending products found');

        ($this->handler)($message);
    }

    public function testHandleContinuesOnDispatchError(): void
    {
        $message = new ScanPendingSyncMessage(
            salesChannelId: null,
            staleMinutes: 5,
            limit: 100
        );

        $product1 = $this->createMock(ChannelProduct::class);
        $product1->method('getId')->willReturn('product-1');
        $product1->method('getExternalId')->willReturn('ext-123');

        $product2 = $this->createMock(ChannelProduct::class);
        $product2->method('getId')->willReturn('product-2');
        $product2->method('getExternalId')->willReturn('ext-456');

        $this->channelProductRepo->expects($this->once())
            ->method('findStalePending')
            ->willReturn([$product1, $product2]);

        // First dispatch fails, second succeeds
        $this->messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new \RuntimeException('Dispatch failed')),
                new Envelope(new PushChannelProductMessage('product-2', PushChannelProductMessage::OPERATION_UPDATE_STOCK_PRICE))
            );

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Failed to re-dispatch sync for stale product',
                $this->callback(function ($context) {
                    return isset($context['channelProductId']) && $context['channelProductId'] === 'product-1';
                })
            );

        // Should continue despite error
        ($this->handler)($message);
    }

    public function testHandleWithSpecificChannel(): void
    {
        $message = new ScanPendingSyncMessage(
            salesChannelId: 'channel-123',
            staleMinutes: 10,
            limit: 50
        );

        $product = $this->createMock(ChannelProduct::class);
        $product->method('getId')->willReturn('product-1');
        $product->method('getExternalId')->willReturn(null);

        $this->channelProductRepo->expects($this->once())
            ->method('findStalePending')
            ->with(
                $this->isInstanceOf(\DateTimeImmutable::class),
                'channel-123',
                50
            )
            ->willReturn([$product]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new PushChannelProductMessage('product-1', PushChannelProductMessage::OPERATION_PUSH_PRODUCT)));

        ($this->handler)($message);
    }
}
