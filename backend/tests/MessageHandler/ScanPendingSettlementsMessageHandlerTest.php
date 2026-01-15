<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Settlement;
use App\Message\ProcessSettlementMessage;
use App\Message\ScanPendingSettlementsMessage;
use App\MessageHandler\ScanPendingSettlementsMessageHandler;
use App\Repository\SettlementRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ScanPendingSettlementsMessageHandlerTest extends TestCase
{
    private SettlementRepository&MockObject $settlementRepository;
    private MessageBusInterface&MockObject $messageBus;
    private LoggerInterface&MockObject $logger;
    private ScanPendingSettlementsMessageHandler $handler;

    protected function setUp(): void
    {
        $this->settlementRepository = $this->createMock(SettlementRepository::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new ScanPendingSettlementsMessageHandler(
            $this->settlementRepository,
            $this->messageBus,
            $this->logger,
        );
    }

    public function testHandleDispatchesForPendingSettlements(): void
    {
        $message = new ScanPendingSettlementsMessage(
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );

        $settlement1 = $this->createMock(Settlement::class);
        $settlement1->method('getId')->willReturn('settlement-1');

        $settlement2 = $this->createMock(Settlement::class);
        $settlement2->method('getId')->willReturn('settlement-2');

        $this->settlementRepository->expects($this->once())
            ->method('findReadyToSettle')
            ->with(100)
            ->willReturn([$settlement1, $settlement2]);

        $this->messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->isInstanceOf(ProcessSettlementMessage::class))
            ->willReturn(new Envelope(ProcessSettlementMessage::create('settlement-1')));

        $this->logger->expects($this->exactly(2))
            ->method('info');

        ($this->handler)($message);
    }

    public function testHandleNoPendingSettlements(): void
    {
        $message = new ScanPendingSettlementsMessage(
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );

        $this->settlementRepository->expects($this->once())
            ->method('findReadyToSettle')
            ->willReturn([]);

        $this->messageBus->expects($this->never())
            ->method('dispatch');

        // Should only log scanning, not dispatched
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Scanning pending settlements',
                $this->callback(function ($context) {
                    return isset($context['count']) && $context['count'] === 0;
                })
            );

        ($this->handler)($message);
    }

    public function testHandleMultipleSettlements(): void
    {
        $message = new ScanPendingSettlementsMessage(
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );

        $settlements = [];
        for ($i = 1; $i <= 5; ++$i) {
            $settlement = $this->createMock(Settlement::class);
            $settlement->method('getId')->willReturn("settlement-{$i}");
            $settlements[] = $settlement;
        }

        $this->settlementRepository->expects($this->once())
            ->method('findReadyToSettle')
            ->willReturn($settlements);

        $this->messageBus->expects($this->exactly(5))
            ->method('dispatch');

        ($this->handler)($message);
    }
}
