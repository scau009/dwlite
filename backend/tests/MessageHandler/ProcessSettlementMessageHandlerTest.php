<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Settlement;
use App\Message\ProcessSettlementMessage;
use App\MessageHandler\ProcessSettlementMessageHandler;
use App\Repository\SettlementRepository;
use App\Service\Settlement\SettlementService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProcessSettlementMessageHandlerTest extends TestCase
{
    private SettlementRepository&MockObject $settlementRepository;
    private SettlementService&MockObject $settlementService;
    private LoggerInterface&MockObject $logger;
    private ProcessSettlementMessageHandler $handler;

    protected function setUp(): void
    {
        $this->settlementRepository = $this->createMock(SettlementRepository::class);
        $this->settlementService = $this->createMock(SettlementService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new ProcessSettlementMessageHandler(
            $this->settlementRepository,
            $this->settlementService,
            $this->logger,
        );
    }

    public function testHandleSuccessfulProcessing(): void
    {
        $message = ProcessSettlementMessage::create('settlement-123');

        $settlement = $this->createMock(Settlement::class);
        $settlement->method('getId')->willReturn('settlement-123');

        $this->settlementRepository->expects($this->once())
            ->method('find')
            ->with('settlement-123')
            ->willReturn($settlement);

        $this->settlementService->expects($this->once())
            ->method('settle')
            ->with($settlement, false);

        ($this->handler)($message);
    }

    public function testHandleSettlementNotFound(): void
    {
        $message = ProcessSettlementMessage::create('settlement-999');

        $this->settlementRepository->expects($this->once())
            ->method('find')
            ->with('settlement-999')
            ->willReturn(null);

        $this->settlementService->expects($this->never())
            ->method('settle');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Settlement not found', ['settlementId' => 'settlement-999']);

        ($this->handler)($message);
    }

    public function testHandleSettlementServiceException(): void
    {
        $message = ProcessSettlementMessage::create('settlement-123');

        $settlement = $this->createMock(Settlement::class);
        $settlement->method('getId')->willReturn('settlement-123');

        $this->settlementRepository->expects($this->once())
            ->method('find')
            ->willReturn($settlement);

        $this->settlementService->expects($this->once())
            ->method('settle')
            ->willThrowException(new \RuntimeException('Settlement is not pending'));

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Settlement could not be processed', [
                'settlementId' => 'settlement-123',
                'reason' => 'Settlement is not pending',
            ]);

        // Should not throw - exception is caught
        ($this->handler)($message);
    }
}
