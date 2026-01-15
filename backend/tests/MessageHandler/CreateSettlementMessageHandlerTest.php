<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Fulfillment;
use App\Entity\FulfillmentItem;
use App\Entity\Merchant;
use App\Entity\Order;
use App\Entity\Settlement;
use App\Message\CreateSettlementMessage;
use App\MessageHandler\CreateSettlementMessageHandler;
use App\Repository\FulfillmentRepository;
use App\Repository\SettlementRepository;
use App\Service\BusinessNoGenerator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

class CreateSettlementMessageHandlerTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private FulfillmentRepository&MockObject $fulfillmentRepository;
    private SettlementRepository&MockObject $settlementRepository;
    private BusinessNoGenerator&MockObject $businessNoGenerator;
    private LockFactory&MockObject $lockFactory;
    private LoggerInterface&MockObject $logger;
    private CreateSettlementMessageHandler $handler;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->fulfillmentRepository = $this->createMock(FulfillmentRepository::class);
        $this->settlementRepository = $this->createMock(SettlementRepository::class);
        $this->businessNoGenerator = $this->createMock(BusinessNoGenerator::class);
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new CreateSettlementMessageHandler(
            $this->entityManager,
            $this->fulfillmentRepository,
            $this->settlementRepository,
            $this->businessNoGenerator,
            $this->lockFactory,
            $this->logger,
        );
    }

    public function testHandleSuccessfulSettlementCreation(): void
    {
        $message = new CreateSettlementMessage('fulfillment-123');

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

        $merchant = $this->createMock(Merchant::class);
        $order = $this->createMock(Order::class);

        $fulfillmentItem = $this->createMock(FulfillmentItem::class);
        $fulfillmentItem->method('getCommissionRate')->willReturn('5.00');

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getId')->willReturn('fulfillment-123');
        $fulfillment->method('isCompleted')->willReturn(true);
        $fulfillment->method('getMerchant')->willReturn($merchant);
        $fulfillment->method('getOrder')->willReturn($order);
        $fulfillment->method('getCompletedAt')->willReturn(
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );
        $fulfillment->method('getItems')->willReturn(
            new ArrayCollection([$fulfillmentItem])
        );

        $this->fulfillmentRepository->expects($this->once())
            ->method('find')
            ->with('fulfillment-123')
            ->willReturn($fulfillment);

        $this->settlementRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['fulfillment' => $fulfillment])
            ->willReturn(null);

        $this->businessNoGenerator->expects($this->once())
            ->method('generateSettlementNo')
            ->willReturn('ST20240115000001');

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Settlement::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        ($this->handler)($message);
    }

    public function testHandleLockAcquisitionFailed(): void
    {
        $message = new CreateSettlementMessage('fulfillment-123');

        $lock = $this->createMock(LockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->with(false)
            ->willReturn(false);

        $this->lockFactory->expects($this->once())
            ->method('createLock')
            ->willReturn($lock);

        $this->fulfillmentRepository->expects($this->never())
            ->method('find');

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Settlement creation already in progress',
                $this->arrayHasKey('fulfillmentId')
            );

        ($this->handler)($message);
    }

    public function testHandleFulfillmentNotFound(): void
    {
        $message = new CreateSettlementMessage('fulfillment-999');

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $this->lockFactory->method('createLock')->willReturn($lock);

        $this->fulfillmentRepository->expects($this->once())
            ->method('find')
            ->willReturn(null);

        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Fulfillment not found',
                $this->arrayHasKey('fulfillmentId')
            );

        ($this->handler)($message);
    }

    public function testHandleFulfillmentNotCompleted(): void
    {
        $message = new CreateSettlementMessage('fulfillment-123');

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $this->lockFactory->method('createLock')->willReturn($lock);

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getId')->willReturn('fulfillment-123');
        $fulfillment->method('isCompleted')->willReturn(false);
        $fulfillment->method('getStatus')->willReturn('shipped');

        $this->fulfillmentRepository->expects($this->once())
            ->method('find')
            ->willReturn($fulfillment);

        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Fulfillment not completed',
                $this->callback(function ($context) {
                    return isset($context['status']) && $context['status'] === 'shipped';
                })
            );

        ($this->handler)($message);
    }

    public function testHandleSettlementAlreadyExists(): void
    {
        $message = new CreateSettlementMessage('fulfillment-123');

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $this->lockFactory->method('createLock')->willReturn($lock);

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('isCompleted')->willReturn(true);

        $existingSettlement = $this->createMock(Settlement::class);
        $existingSettlement->method('getId')->willReturn('settlement-456');

        $this->fulfillmentRepository->expects($this->once())
            ->method('find')
            ->willReturn($fulfillment);

        $this->settlementRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existingSettlement);

        $this->businessNoGenerator->expects($this->never())
            ->method('generateSettlementNo');

        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Settlement already exists',
                $this->callback(function ($context) {
                    return isset($context['settlementId']) && $context['settlementId'] === 'settlement-456';
                })
            );

        ($this->handler)($message);
    }
}
