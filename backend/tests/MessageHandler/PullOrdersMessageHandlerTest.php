<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\SalesChannel;
use App\Message\PullOrdersMessage;
use App\MessageHandler\PullOrdersMessageHandler;
use App\Repository\SalesChannelRepository;
use App\Service\ChannelGateway\Exception\ChannelRateLimitException;
use App\Service\OrderSyncService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class PullOrdersMessageHandlerTest extends TestCase
{
    private SalesChannelRepository&MockObject $salesChannelRepo;
    private OrderSyncService&MockObject $orderSyncService;
    private LockFactory&MockObject $lockFactory;
    private MessageBusInterface&MockObject $messageBus;
    private LoggerInterface&MockObject $logger;
    private PullOrdersMessageHandler $handler;

    protected function setUp(): void
    {
        $this->salesChannelRepo = $this->createMock(SalesChannelRepository::class);
        $this->orderSyncService = $this->createMock(OrderSyncService::class);
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new PullOrdersMessageHandler(
            $this->salesChannelRepo,
            $this->orderSyncService,
            $this->lockFactory,
            $this->messageBus,
            $this->logger,
        );
    }

    public function testHandleSuccessfulPull(): void
    {
        $startTime = new \DateTimeImmutable('-1 hour', new \DateTimeZone('UTC'));
        $endTime = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $message = new PullOrdersMessage('channel-123', $startTime, $endTime, 1, 50);

        $lock = $this->createMock(LockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->with(false)
            ->willReturn(true);
        $lock->expects($this->once())
            ->method('release');

        $this->lockFactory->expects($this->once())
            ->method('createLock')
            ->willReturn($lock);

        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getId')->willReturn('channel-123');
        $channel->method('isActive')->willReturn(true);

        $this->salesChannelRepo->expects($this->once())
            ->method('find')
            ->with('channel-123')
            ->willReturn($channel);

        $this->orderSyncService->expects($this->once())
            ->method('pullOrders')
            ->with($channel, $startTime, $endTime, 1, 50)
            ->willReturn([
                'created' => 10,
                'updated' => 5,
                'skipped' => 2,
                'errors' => 0,
            ]);

        // Should not dispatch next page (17 < 50)
        $this->messageBus->expects($this->never())
            ->method('dispatch');

        ($this->handler)($message);
    }

    public function testHandleWithNextPage(): void
    {
        $startTime = new \DateTimeImmutable('-1 hour', new \DateTimeZone('UTC'));
        $endTime = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $message = new PullOrdersMessage('channel-123', $startTime, $endTime, 1, 50);

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $this->lockFactory->method('createLock')->willReturn($lock);

        $channel = $this->createMock(SalesChannel::class);
        $channel->method('isActive')->willReturn(true);

        $this->salesChannelRepo->method('find')->willReturn($channel);

        $this->orderSyncService->expects($this->once())
            ->method('pullOrders')
            ->willReturn([
                'created' => 50,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0,
            ]);

        // Should dispatch next page
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(PullOrdersMessage::class))
            ->willReturn(new Envelope(new PullOrdersMessage('channel-123', $startTime, $endTime, 2, 50)));

        ($this->handler)($message);
    }

    public function testHandleLockAcquisitionFailed(): void
    {
        $startTime = new \DateTimeImmutable('-1 hour', new \DateTimeZone('UTC'));
        $endTime = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $message = new PullOrdersMessage('channel-123', $startTime, $endTime, 1, 50);

        $lock = $this->createMock(LockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->with(false)
            ->willReturn(false);

        $this->lockFactory->expects($this->once())
            ->method('createLock')
            ->willReturn($lock);

        // Should not proceed with pull
        $this->salesChannelRepo->expects($this->never())
            ->method('find');

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Order pull already in progress, skipping',
                $this->arrayHasKey('salesChannelId')
            );

        ($this->handler)($message);
    }

    public function testHandleChannelNotFound(): void
    {
        $startTime = new \DateTimeImmutable('-1 hour', new \DateTimeZone('UTC'));
        $endTime = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $message = new PullOrdersMessage('channel-999', $startTime, $endTime, 1, 50);

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $this->lockFactory->method('createLock')->willReturn($lock);

        $this->salesChannelRepo->expects($this->once())
            ->method('find')
            ->willReturn(null);

        $this->orderSyncService->expects($this->never())
            ->method('pullOrders');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Sales channel not found or inactive',
                $this->arrayHasKey('salesChannelId')
            );

        ($this->handler)($message);
    }

    public function testHandleChannelInactive(): void
    {
        $startTime = new \DateTimeImmutable('-1 hour', new \DateTimeZone('UTC'));
        $endTime = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $message = new PullOrdersMessage('channel-123', $startTime, $endTime, 1, 50);

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $this->lockFactory->method('createLock')->willReturn($lock);

        $channel = $this->createMock(SalesChannel::class);
        $channel->method('isActive')->willReturn(false);

        $this->salesChannelRepo->method('find')->willReturn($channel);

        $this->orderSyncService->expects($this->never())
            ->method('pullOrders');

        ($this->handler)($message);
    }

    public function testHandleRateLimitException(): void
    {
        $startTime = new \DateTimeImmutable('-1 hour', new \DateTimeZone('UTC'));
        $endTime = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $message = new PullOrdersMessage('channel-123', $startTime, $endTime, 1, 50);

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $this->lockFactory->method('createLock')->willReturn($lock);

        $channel = $this->createMock(SalesChannel::class);
        $channel->method('isActive')->willReturn(true);

        $this->salesChannelRepo->method('find')->willReturn($channel);

        $exception = new ChannelRateLimitException('Rate limited', 60);

        $this->orderSyncService->expects($this->once())
            ->method('pullOrders')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Rate limited during order pull',
                $this->arrayHasKey('retryAfter')
            );

        $this->expectException(ChannelRateLimitException::class);

        ($this->handler)($message);
    }

    public function testHandleGenericException(): void
    {
        $startTime = new \DateTimeImmutable('-1 hour', new \DateTimeZone('UTC'));
        $endTime = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $message = new PullOrdersMessage('channel-123', $startTime, $endTime, 1, 50);

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $this->lockFactory->method('createLock')->willReturn($lock);

        $channel = $this->createMock(SalesChannel::class);
        $channel->method('isActive')->willReturn(true);

        $this->salesChannelRepo->method('find')->willReturn($channel);

        $exception = new \RuntimeException('Pull failed');

        $this->orderSyncService->expects($this->once())
            ->method('pullOrders')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error');

        $this->expectException(\RuntimeException::class);

        ($this->handler)($message);
    }
}
