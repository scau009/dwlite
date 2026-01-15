<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Merchant;
use App\Entity\Settlement;
use App\Entity\Wallet;
use App\Message\ProcessSettlementMessage;
use App\MessageHandler\ProcessSettlementMessageHandler;
use App\Repository\SettlementRepository;
use App\Repository\WalletRepository;
use App\Service\BusinessNoGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

class ProcessSettlementMessageHandlerTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private SettlementRepository&MockObject $settlementRepository;
    private WalletRepository&MockObject $walletRepository;
    private BusinessNoGenerator&MockObject $businessNoGenerator;
    private LockFactory&MockObject $lockFactory;
    private LoggerInterface&MockObject $logger;
    private ProcessSettlementMessageHandler $handler;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->settlementRepository = $this->createMock(SettlementRepository::class);
        $this->walletRepository = $this->createMock(WalletRepository::class);
        $this->businessNoGenerator = $this->createMock(BusinessNoGenerator::class);
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new ProcessSettlementMessageHandler(
            $this->entityManager,
            $this->settlementRepository,
            $this->walletRepository,
            $this->businessNoGenerator,
            $this->lockFactory,
            $this->logger,
        );
    }

    public function testHandleSuccessfulProcessing(): void
    {
        $message = ProcessSettlementMessage::create('settlement-123');

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $this->lockFactory->method('createLock')->willReturn($lock);

        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $settlement = $this->createMock(Settlement::class);
        $settlement->method('getId')->willReturn('settlement-123');
        $settlement->method('getSettlementNo')->willReturn('ST20240115000001');
        $settlement->method('canSettle')->willReturn(true);
        $settlement->method('getMerchant')->willReturn($merchant);
        $settlement->method('getNetAmount')->willReturn('1000.00');

        $this->settlementRepository->expects($this->once())
            ->method('find')
            ->willReturn($settlement);

        $wallet = $this->createMock(Wallet::class);
        $wallet->method('getBalance')->willReturn('5000.00');
        $wallet->expects($this->once())
            ->method('credit')
            ->with('1000.00');

        $this->walletRepository->expects($this->once())
            ->method('findBalanceWallet')
            ->with($merchant)
            ->willReturn($wallet);

        $this->businessNoGenerator->expects($this->once())
            ->method('generateWalletTransactionNo')
            ->willReturn('WT20240115000001');

        $settlement->expects($this->once())
            ->method('markSettled');

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        ($this->handler)($message);
    }

    public function testHandleLockAcquisitionFailed(): void
    {
        $message = ProcessSettlementMessage::create('settlement-123');

        $lock = $this->createMock(LockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->with(false)
            ->willReturn(false);

        $this->lockFactory->method('createLock')->willReturn($lock);

        $this->settlementRepository->expects($this->never())
            ->method('find');

        ($this->handler)($message);
    }

    public function testHandleSettlementNotFound(): void
    {
        $message = ProcessSettlementMessage::create('settlement-999');

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $this->lockFactory->method('createLock')->willReturn($lock);

        $this->settlementRepository->expects($this->once())
            ->method('find')
            ->willReturn(null);

        $this->walletRepository->expects($this->never())
            ->method('findBalanceWallet');

        ($this->handler)($message);
    }

    public function testHandleSettlementCannotSettle(): void
    {
        $message = ProcessSettlementMessage::create('settlement-123');

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $this->lockFactory->method('createLock')->willReturn($lock);

        $settlement = $this->createMock(Settlement::class);
        $settlement->method('canSettle')->willReturn(false);
        $settlement->method('getStatus')->willReturn('settled');
        $settlement->method('getScheduledSettleAt')->willReturn(
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );

        $this->settlementRepository->expects($this->once())
            ->method('find')
            ->willReturn($settlement);

        $this->walletRepository->expects($this->never())
            ->method('findBalanceWallet');

        ($this->handler)($message);
    }

    public function testHandleWalletNotFound(): void
    {
        $message = ProcessSettlementMessage::create('settlement-123');

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $this->lockFactory->method('createLock')->willReturn($lock);

        $merchant = $this->createMock(Merchant::class);

        $settlement = $this->createMock(Settlement::class);
        $settlement->method('canSettle')->willReturn(true);
        $settlement->method('getMerchant')->willReturn($merchant);

        $this->settlementRepository->expects($this->once())
            ->method('find')
            ->willReturn($settlement);

        $this->walletRepository->expects($this->once())
            ->method('findBalanceWallet')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Balance wallet not found');

        ($this->handler)($message);
    }
}
