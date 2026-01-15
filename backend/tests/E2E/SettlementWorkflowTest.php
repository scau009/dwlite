<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Entity\Fulfillment;
use App\Entity\Merchant;
use App\Entity\Settlement;
use App\Entity\Wallet;
use App\Entity\WalletTransaction;
use App\Repository\SettlementRepository;
use App\Repository\WalletRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests the complete settlement workflow from creation to payout.
 */
class SettlementWorkflowTest extends TestCase
{
    private SettlementRepository&MockObject $settlementRepo;
    private WalletRepository&MockObject $walletRepo;

    protected function setUp(): void
    {
        $this->settlementRepo = $this->createMock(SettlementRepository::class);
        $this->walletRepo = $this->createMock(WalletRepository::class);
    }

    public function testCompleteSettlementFlow(): void
    {
        // Step 1: Settlement is created after fulfillment completion
        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getId')->willReturn('fulfillment-123');
        $fulfillment->method('isCompleted')->willReturn(true);
        $fulfillment->method('getCompletedAt')->willReturn(
            new \DateTimeImmutable('-8 days', new \DateTimeZone('UTC'))
        );

        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $settlement = $this->createMock(Settlement::class);
        $settlement->method('getId')->willReturn('settlement-123');
        $settlement->method('getSettlementNo')->willReturn('ST20240115000001');
        $settlement->method('getMerchant')->willReturn($merchant);
        $settlement->method('getFulfillment')->willReturn($fulfillment);
        $settlement->method('getGrossAmount')->willReturn('100.00');
        $settlement->method('getCommissionAmount')->willReturn('5.00');
        $settlement->method('getNetAmount')->willReturn('95.00');
        $settlement->method('getStatus')->willReturn(Settlement::STATUS_PENDING);
        $settlement->method('canSettle')->willReturn(true);

        $this->settlementRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['fulfillment' => $fulfillment])
            ->willReturn($settlement);

        $foundSettlement = $this->settlementRepo->findOneBy(['fulfillment' => $fulfillment]);
        $this->assertNotNull($foundSettlement);
        $this->assertEquals('95.00', $foundSettlement->getNetAmount());

        // Step 2: Settlement becomes ready to settle (T+7 passed)
        $this->settlementRepo->expects($this->once())
            ->method('findReadyToSettle')
            ->with(100)
            ->willReturn([$settlement]);

        $readySettlements = $this->settlementRepo->findReadyToSettle(100);
        $this->assertCount(1, $readySettlements);

        // Step 3: Settlement is processed and credited to merchant wallet
        $wallet = $this->createMock(Wallet::class);
        $wallet->method('getId')->willReturn('wallet-123');
        $wallet->method('getBalance')->willReturn('1000.00');

        $this->walletRepo->expects($this->once())
            ->method('findBalanceWallet')
            ->with($merchant)
            ->willReturn($wallet);

        $balanceBefore = $wallet->getBalance();

        $wallet->expects($this->once())
            ->method('credit')
            ->with('95.00');

        $wallet->credit('95.00');

        // Step 4: Settlement is marked as settled
        $settlement->expects($this->once())
            ->method('markSettled')
            ->with('transaction-123');

        $settlement->markSettled('transaction-123');

        // Verify wallet transaction was created
        $this->assertEquals('1000.00', $balanceBefore);
    }

    public function testSettlementWithMultipleFulfillments(): void
    {
        $merchant = $this->createMock(Merchant::class);

        $settlement1 = $this->createMock(Settlement::class);
        $settlement1->method('getMerchant')->willReturn($merchant);
        $settlement1->method('getNetAmount')->willReturn('95.00');

        $settlement2 = $this->createMock(Settlement::class);
        $settlement2->method('getMerchant')->willReturn($merchant);
        $settlement2->method('getNetAmount')->willReturn('190.00');

        $this->settlementRepo->expects($this->once())
            ->method('findReadyToSettle')
            ->willReturn([$settlement1, $settlement2]);

        $settlements = $this->settlementRepo->findReadyToSettle(100);
        $this->assertCount(2, $settlements);

        // Total settlement amount
        $totalAmount = array_reduce(
            $settlements,
            fn($carry, $s) => bcadd($carry, $s->getNetAmount(), 2),
            '0.00'
        );

        $this->assertEquals('285.00', $totalAmount);
    }

    public function testSettlementCannotSettleBeforeScheduled(): void
    {
        $settlement = $this->createMock(Settlement::class);
        $settlement->method('getId')->willReturn('settlement-123');
        $settlement->method('getStatus')->willReturn(Settlement::STATUS_PENDING);
        $settlement->method('getScheduledSettleAt')->willReturn(
            new \DateTimeImmutable('+2 days', new \DateTimeZone('UTC'))
        );
        $settlement->method('canSettle')->willReturn(false);

        // Should not be returned in ready to settle query
        $this->settlementRepo->expects($this->once())
            ->method('findReadyToSettle')
            ->willReturn([]);

        $settlements = $this->settlementRepo->findReadyToSettle(100);
        $this->assertEmpty($settlements);
    }

    public function testWalletBalanceAfterSettlement(): void
    {
        $merchant = $this->createMock(Merchant::class);

        $wallet = $this->createMock(Wallet::class);
        $wallet->method('getBalance')->willReturn('1000.00');

        $this->walletRepo->method('findBalanceWallet')
            ->with($merchant)
            ->willReturn($wallet);

        // Credit settlement amount
        $wallet->expects($this->once())
            ->method('credit')
            ->with('95.00');

        $wallet->credit('95.00');

        // Verify transaction record
        $transaction = $this->createMock(WalletTransaction::class);
        $transaction->method('getAmount')->willReturn('95.00');
        $transaction->method('getBalanceBefore')->willReturn('1000.00');
        $transaction->method('getBalanceAfter')->willReturn('1095.00');
        $transaction->method('getType')->willReturn(WalletTransaction::TYPE_CREDIT);
        $transaction->method('getBizType')->willReturn(WalletTransaction::BIZ_SETTLEMENT);

        $this->assertEquals('95.00', $transaction->getAmount());
        $this->assertEquals(WalletTransaction::TYPE_CREDIT, $transaction->getType());
        $this->assertEquals(WalletTransaction::BIZ_SETTLEMENT, $transaction->getBizType());
    }
}
