<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\SalesChannel;
use App\Message\PullOrdersMessage;
use App\Message\ScheduleOrderPullMessage;
use App\MessageHandler\ScheduleOrderPullMessageHandler;
use App\Repository\SalesChannelRepository;
use App\Service\ChannelGateway\ChannelGatewayInterface;
use App\Service\ChannelGateway\ChannelGatewayRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ScheduleOrderPullMessageHandlerTest extends TestCase
{
    private SalesChannelRepository&MockObject $salesChannelRepo;
    private ChannelGatewayRegistry&MockObject $gatewayRegistry;
    private MessageBusInterface&MockObject $messageBus;
    private LoggerInterface&MockObject $logger;
    private ScheduleOrderPullMessageHandler $handler;

    protected function setUp(): void
    {
        $this->salesChannelRepo = $this->createMock(SalesChannelRepository::class);
        $this->gatewayRegistry = $this->createMock(ChannelGatewayRegistry::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new ScheduleOrderPullMessageHandler(
            $this->salesChannelRepo,
            $this->gatewayRegistry,
            $this->messageBus,
            $this->logger,
        );
    }

    public function testHandleDispatchesForActiveChannels(): void
    {
        $message = new ScheduleOrderPullMessage(
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );

        $channel1 = $this->createMock(SalesChannel::class);
        $channel1->method('getId')->willReturn('channel-1');
        $channel1->method('getCode')->willReturn('kickscrew');

        $channel2 = $this->createMock(SalesChannel::class);
        $channel2->method('getId')->willReturn('channel-2');
        $channel2->method('getCode')->willReturn('dewu');

        $this->salesChannelRepo->expects($this->once())
            ->method('findActive')
            ->willReturn([$channel1, $channel2]);

        $this->gatewayRegistry->expects($this->exactly(2))
            ->method('has')
            ->willReturnMap([
                ['kickscrew', true],
                ['dewu', true],
            ]);

        $gateway1 = $this->createMock(ChannelGatewayInterface::class);
        $gateway1->expects($this->once())
            ->method('supports')
            ->with(ChannelGatewayInterface::OPERATION_PULL_ORDERS)
            ->willReturn(true);

        $gateway2 = $this->createMock(ChannelGatewayInterface::class);
        $gateway2->expects($this->once())
            ->method('supports')
            ->with(ChannelGatewayInterface::OPERATION_PULL_ORDERS)
            ->willReturn(true);

        $this->gatewayRegistry->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['kickscrew', $gateway1],
                ['dewu', $gateway2],
            ]);

        $this->messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->isInstanceOf(PullOrdersMessage::class))
            ->willReturn(new Envelope($this->createMock(PullOrdersMessage::class)));

        ($this->handler)($message);
    }

    public function testHandleSkipsChannelWithoutGateway(): void
    {
        $message = new ScheduleOrderPullMessage(
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );

        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getCode')->willReturn('unknown');

        $this->salesChannelRepo->expects($this->once())
            ->method('findActive')
            ->willReturn([$channel]);

        $this->gatewayRegistry->expects($this->once())
            ->method('has')
            ->with('unknown')
            ->willReturn(false);

        // Should not dispatch for channel without gateway
        $this->messageBus->expects($this->never())
            ->method('dispatch');

        ($this->handler)($message);
    }

    public function testHandleSkipsChannelNotSupportingPull(): void
    {
        $message = new ScheduleOrderPullMessage(
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );

        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getCode')->willReturn('kickscrew');

        $this->salesChannelRepo->expects($this->once())
            ->method('findActive')
            ->willReturn([$channel]);

        $this->gatewayRegistry->method('has')->willReturn(true);

        $gateway = $this->createMock(ChannelGatewayInterface::class);
        $gateway->expects($this->once())
            ->method('supports')
            ->with(ChannelGatewayInterface::OPERATION_PULL_ORDERS)
            ->willReturn(false);

        $this->gatewayRegistry->method('get')->willReturn($gateway);

        // Should not dispatch for channel not supporting pull
        $this->messageBus->expects($this->never())
            ->method('dispatch');

        ($this->handler)($message);
    }

    public function testHandleContinuesOnDispatchError(): void
    {
        $message = new ScheduleOrderPullMessage(
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );

        $channel1 = $this->createMock(SalesChannel::class);
        $channel1->method('getId')->willReturn('channel-1');
        $channel1->method('getCode')->willReturn('kickscrew');

        $channel2 = $this->createMock(SalesChannel::class);
        $channel2->method('getId')->willReturn('channel-2');
        $channel2->method('getCode')->willReturn('dewu');

        $this->salesChannelRepo->expects($this->once())
            ->method('findActive')
            ->willReturn([$channel1, $channel2]);

        $this->gatewayRegistry->method('has')->willReturn(true);

        $gateway = $this->createMock(ChannelGatewayInterface::class);
        $gateway->method('supports')->willReturn(true);
        $this->gatewayRegistry->method('get')->willReturn($gateway);

        // First dispatch fails, second succeeds
        $this->messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new \RuntimeException('Dispatch failed')),
                new Envelope($this->createMock(PullOrdersMessage::class))
            );

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Failed to dispatch order pull',
                $this->callback(function ($context) {
                    return isset($context['error']) && $context['error'] === 'Dispatch failed';
                })
            );

        // Should continue despite error
        ($this->handler)($message);
    }

    public function testHandleNoActiveChannels(): void
    {
        $message = new ScheduleOrderPullMessage(
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );

        $this->salesChannelRepo->expects($this->once())
            ->method('findActive')
            ->willReturn([]);

        $this->messageBus->expects($this->never())
            ->method('dispatch');

        ($this->handler)($message);
    }
}
