<?php

declare(strict_types=1);

namespace App\Tests\Service\Settlement;

use App\Entity\Fulfillment;
use App\Entity\Merchant;
use App\Entity\Order;
use App\Entity\Settlement;
use App\Entity\Wallet;
use App\Repository\WalletRepository;
use App\Service\BusinessNoGenerator;
use App\Service\OpenApi\WebhookService;
use App\Service\Settlement\SettlementService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

class SettlementServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private WalletRepository&MockObject $walletRepository;
    private BusinessNoGenerator&MockObject $businessNoGenerator;
    private WebhookService&MockObject $webhookService;
    private LockFactory&MockObject $lockFactory;
    private LoggerInterface&MockObject $logger;
    private SettlementService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->walletRepository = $this->createMock(WalletRepository::class);
        $this->businessNoGenerator = $this->createMock(BusinessNoGenerator::class);
        $this->webhookService = $this->createMock(WebhookService::class);
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new SettlementService(
            $this->entityManager,
            $this->walletRepository,
            $this->businessNoGenerator,
            $this->webhookService,
            $this->lockFactory,
            $this->logger,
        );
    }

    public function testSettleSuccessfully(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getFulfillmentNo')->willReturn('FF20240115000001');

        $order = $this->createMock(Order::class);
        $order->method('getExternalOrderId')->willReturn('EXT-001');

        $settlement = $this->createMock(Settlement::class);
        $settlement->method('getId')->willReturn('settlement-123');
        $settlement->method('getSettlementNo')->willReturn('ST20240115000001');
        $settlement->method('isPending')->willReturn(true);
        $settlement->method('canSettle')->willReturn(true);
        $settlement->method('getMerchant')->willReturn($merchant);
        $settlement->method('getFulfillment')->willReturn($fulfillment);
        $settlement->method('getOrder')->willReturn($order);
        $settlement->method('getNetAmount')->willReturn('1000.00');
        $settlement->method('getCurrency')->willReturn('CNY');
        $settlement->method('getStatus')->willReturn(Settlement::STATUS_SETTLED);
        $settlement->method('getSettledAt')->willReturn(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');
        $this->lockFactory->method('createLock')->willReturn($lock);

        $wallet = $this->createMock(Wallet::class);
        $wallet->method('getBalance')->willReturn('5000.00');
        $wallet->expects($this->once())->method('credit')->with('1000.00');

        $this->walletRepository->expects($this->once())
            ->method('findBalanceWallet')
            ->with($merchant)
            ->willReturn($wallet);

        $this->businessNoGenerator->expects($this->once())
            ->method('generateWalletTransactionNo')
            ->willReturn('WT20240115000001');

        $settlement->expects($this->once())->method('markSettled');

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $this->webhookService->expects($this->once())
            ->method('triggerMerchantEvent');

        $this->service->settle($settlement, force: false);
    }

    public function testSettleWithForce(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getFulfillmentNo')->willReturn('FF20240115000001');

        $order = $this->createMock(Order::class);
        $order->method('getExternalOrderId')->willReturn('EXT-001');

        $settlement = $this->createMock(Settlement::class);
        $settlement->method('getId')->willReturn('settlement-123');
        $settlement->method('getSettlementNo')->willReturn('ST20240115000001');
        $settlement->method('isPending')->willReturn(true);
        // canSettle returns false because scheduled time hasn't arrived
        $settlement->method('canSettle')->willReturn(false);
        $settlement->method('getMerchant')->willReturn($merchant);
        $settlement->method('getFulfillment')->willReturn($fulfillment);
        $settlement->method('getOrder')->willReturn($order);
        $settlement->method('getNetAmount')->willReturn('1000.00');
        $settlement->method('getCurrency')->willReturn('CNY');
        $settlement->method('getStatus')->willReturn(Settlement::STATUS_SETTLED);
        $settlement->method('getSettledAt')->willReturn(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');
        $this->lockFactory->method('createLock')->willReturn($lock);

        $wallet = $this->createMock(Wallet::class);
        $wallet->method('getBalance')->willReturn('5000.00');
        $wallet->expects($this->once())->method('credit')->with('1000.00');

        $this->walletRepository->expects($this->once())
            ->method('findBalanceWallet')
            ->willReturn($wallet);

        $this->businessNoGenerator->expects($this->once())
            ->method('generateWalletTransactionNo')
            ->willReturn('WT20240115000001');

        $settlement->expects($this->once())->method('markSettled');

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        // With force=true, it should process even though canSettle is false
        $this->service->settle($settlement, force: true);
    }

    public function testSettleThrowsWhenLockCannotBeAcquired(): void
    {
        $settlement = $this->createMock(Settlement::class);
        $settlement->method('getId')->willReturn('settlement-123');

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(false);
        $this->lockFactory->method('createLock')->willReturn($lock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Settlement processing already in progress');

        $this->service->settle($settlement);
    }

    public function testSettleThrowsWhenNotPending(): void
    {
        $settlement = $this->createMock(Settlement::class);
        $settlement->method('getId')->willReturn('settlement-123');
        $settlement->method('isPending')->willReturn(false);
        $settlement->method('getStatus')->willReturn(Settlement::STATUS_SETTLED);

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');
        $this->lockFactory->method('createLock')->willReturn($lock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Settlement is not pending');

        $this->service->settle($settlement);
    }

    public function testSettleThrowsWhenScheduledTimeNotArrived(): void
    {
        $settlement = $this->createMock(Settlement::class);
        $settlement->method('getId')->willReturn('settlement-123');
        $settlement->method('isPending')->willReturn(true);
        $settlement->method('canSettle')->willReturn(false);
        $settlement->method('getScheduledSettleAt')->willReturn(
            new \DateTimeImmutable('+1 day', new \DateTimeZone('UTC'))
        );

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');
        $this->lockFactory->method('createLock')->willReturn($lock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Settlement scheduled time has not arrived');

        // Without force=true, it should throw
        $this->service->settle($settlement, force: false);
    }

    public function testSettleThrowsWhenWalletNotFound(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $settlement = $this->createMock(Settlement::class);
        $settlement->method('getId')->willReturn('settlement-123');
        $settlement->method('isPending')->willReturn(true);
        $settlement->method('canSettle')->willReturn(true);
        $settlement->method('getMerchant')->willReturn($merchant);

        $lock = $this->createMock(LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');
        $this->lockFactory->method('createLock')->willReturn($lock);

        $this->walletRepository->expects($this->once())
            ->method('findBalanceWallet')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Balance wallet not found');

        $this->service->settle($settlement);
    }
}
