<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Fulfillment;
use App\Entity\OutboundOrder;
use App\Message\ProcessConsignmentFulfillmentMessage;
use App\MessageHandler\ProcessConsignmentFulfillmentMessageHandler;
use App\Repository\FulfillmentRepository;
use App\Service\Fulfillment\OutboundOrderCreationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProcessConsignmentFulfillmentMessageHandlerTest extends TestCase
{
    private FulfillmentRepository&MockObject $fulfillmentRepo;
    private OutboundOrderCreationService&MockObject $outboundOrderCreationService;
    private EntityManagerInterface&MockObject $entityManager;
    private LoggerInterface&MockObject $logger;
    private ProcessConsignmentFulfillmentMessageHandler $handler;

    protected function setUp(): void
    {
        $this->fulfillmentRepo = $this->createMock(FulfillmentRepository::class);
        $this->outboundOrderCreationService = $this->createMock(OutboundOrderCreationService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new ProcessConsignmentFulfillmentMessageHandler(
            $this->fulfillmentRepo,
            $this->outboundOrderCreationService,
            $this->entityManager,
            $this->logger,
        );
    }

    public function testProcessConsignmentFulfillmentSuccess(): void
    {
        $fulfillmentId = '01HTEST123456789ABCDEFGH';
        $message = new ProcessConsignmentFulfillmentMessage($fulfillmentId);

        // Setup fulfillment
        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getId')->willReturn($fulfillmentId);
        $fulfillment->method('getFulfillmentNo')->willReturn('FF20250114000001');
        $fulfillment->method('isPlatformWarehouse')->willReturn(true);
        $fulfillment->method('isPending')->willReturn(true);

        $this->fulfillmentRepo->expects($this->once())
            ->method('find')
            ->with($fulfillmentId)
            ->willReturn($fulfillment);

        // Expect markProcessing to be called
        $fulfillment->expects($this->once())->method('markProcessing');

        // Setup outbound order
        $outboundOrder = $this->createMock(OutboundOrder::class);
        $outboundOrder->method('getId')->willReturn('01HOUTBOUND123456');
        $outboundOrder->method('getOutboundNo')->willReturn('OB20250114000001');

        $this->outboundOrderCreationService->expects($this->once())
            ->method('createFromFulfillment')
            ->with($fulfillment)
            ->willReturn($outboundOrder);

        // Expect setOutboundOrder to be called
        $fulfillment->expects($this->once())
            ->method('setOutboundOrder')
            ->with($outboundOrder);

        // Expect persist and flush
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($outboundOrder);
        $this->entityManager->expects($this->once())
            ->method('flush');

        ($this->handler)($message);
    }

    public function testSkipWhenFulfillmentNotFound(): void
    {
        $fulfillmentId = '01HTEST123456789ABCDEFGH';
        $message = new ProcessConsignmentFulfillmentMessage($fulfillmentId);

        $this->fulfillmentRepo->expects($this->once())
            ->method('find')
            ->with($fulfillmentId)
            ->willReturn(null);

        // Should not create outbound order
        $this->outboundOrderCreationService->expects($this->never())
            ->method('createFromFulfillment');

        ($this->handler)($message);
    }

    public function testSkipWhenNotPlatformWarehouse(): void
    {
        $fulfillmentId = '01HTEST123456789ABCDEFGH';
        $message = new ProcessConsignmentFulfillmentMessage($fulfillmentId);

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('isPlatformWarehouse')->willReturn(false);
        $fulfillment->method('getFulfillmentType')->willReturn(Fulfillment::TYPE_MERCHANT_WAREHOUSE);

        $this->fulfillmentRepo->expects($this->once())
            ->method('find')
            ->with($fulfillmentId)
            ->willReturn($fulfillment);

        // Should not create outbound order for merchant warehouse
        $this->outboundOrderCreationService->expects($this->never())
            ->method('createFromFulfillment');

        ($this->handler)($message);
    }

    public function testSkipWhenNotPending(): void
    {
        $fulfillmentId = '01HTEST123456789ABCDEFGH';
        $message = new ProcessConsignmentFulfillmentMessage($fulfillmentId);

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('isPlatformWarehouse')->willReturn(true);
        $fulfillment->method('isPending')->willReturn(false);
        $fulfillment->method('getStatus')->willReturn(Fulfillment::STATUS_PROCESSING);

        $this->fulfillmentRepo->expects($this->once())
            ->method('find')
            ->with($fulfillmentId)
            ->willReturn($fulfillment);

        // Should not create outbound order if already processing
        $this->outboundOrderCreationService->expects($this->never())
            ->method('createFromFulfillment');

        ($this->handler)($message);
    }
}
