<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Merchant;
use App\Entity\Wallet;
use App\Entity\WalletTransaction;
use App\Repository\WalletRepository;
use App\Service\BusinessNoGenerator;
use App\Service\WalletService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class WalletServiceTest extends TestCase
{
    private WalletRepository&MockObject $walletRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private TranslatorInterface&MockObject $translator;
    private BusinessNoGenerator&MockObject $businessNoGenerator;
    private WalletService $service;

    protected function setUp(): void
    {
        $this->walletRepository = $this->createMock(WalletRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->businessNoGenerator = $this->createMock(BusinessNoGenerator::class);

        $this->translator->method('trans')->willReturnCallback(fn(string $key) => $key);

        $this->service = new WalletService(
            $this->walletRepository,
            $this->entityManager,
            $this->translator,
            $this->businessNoGenerator,
        );
    }

    public function testInitWallets(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $this->walletRepository->expects($this->once())
            ->method('findByMerchant')
            ->with($merchant)
            ->willReturn([]);

        $depositWallet = $this->createMock(Wallet::class);
        $balanceWallet = $this->createMock(Wallet::class);

        $this->walletRepository->expects($this->once())
            ->method('createWalletsForMerchant')
            ->with($merchant)
            ->willReturn([$depositWallet, $balanceWallet]);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->initWallets($merchant);

        $this->assertCount(2, $result);
    }

    public function testInitWalletsAlreadyExists(): void
    {
        $merchant = $this->createMock(Merchant::class);

        $existingWallet = $this->createMock(Wallet::class);
        $this->walletRepository->expects($this->once())
            ->method('findByMerchant')
            ->with($merchant)
            ->willReturn([$existingWallet]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('wallet.already_initialized');

        $this->service->initWallets($merchant);
    }

    public function testChargeDeposit(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $wallet = $this->createMock(Wallet::class);
        $wallet->method('getBalance')->willReturn('100.00');

        $this->walletRepository->expects($this->once())
            ->method('findDepositWallet')
            ->with($merchant)
            ->willReturn($wallet);

        $wallet->expects($this->once())
            ->method('credit')
            ->with('50.00');

        $this->businessNoGenerator->expects($this->once())
            ->method('generateWalletTransactionNo')
            ->willReturn('WT20240115000001');

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(WalletTransaction::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->chargeDeposit($merchant, '50.00', 'Test charge', 'admin-123');

        $this->assertInstanceOf(WalletTransaction::class, $result);
    }

    public function testChargeDepositWalletNotFound(): void
    {
        $merchant = $this->createMock(Merchant::class);

        $this->walletRepository->expects($this->once())
            ->method('findDepositWallet')
            ->with($merchant)
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('wallet.deposit_not_found');

        $this->service->chargeDeposit($merchant, '50.00');
    }

    public function testChargeDepositWithNegativeAmount(): void
    {
        $merchant = $this->createMock(Merchant::class);

        $wallet = $this->createMock(Wallet::class);
        $this->walletRepository->expects($this->once())
            ->method('findDepositWallet')
            ->with($merchant)
            ->willReturn($wallet);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('wallet.amount_positive');

        $this->service->chargeDeposit($merchant, '-50.00');
    }

    public function testChargeDepositWithZeroAmount(): void
    {
        $merchant = $this->createMock(Merchant::class);

        $wallet = $this->createMock(Wallet::class);
        $this->walletRepository->expects($this->once())
            ->method('findDepositWallet')
            ->with($merchant)
            ->willReturn($wallet);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('wallet.amount_positive');

        $this->service->chargeDeposit($merchant, '0.00');
    }

    public function testGetDepositWallet(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $wallet = $this->createMock(Wallet::class);

        $this->walletRepository->expects($this->once())
            ->method('findDepositWallet')
            ->with($merchant)
            ->willReturn($wallet);

        $result = $this->service->getDepositWallet($merchant);

        $this->assertSame($wallet, $result);
    }

    public function testGetBalanceWallet(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $wallet = $this->createMock(Wallet::class);

        $this->walletRepository->expects($this->once())
            ->method('findBalanceWallet')
            ->with($merchant)
            ->willReturn($wallet);

        $result = $this->service->getBalanceWallet($merchant);

        $this->assertSame($wallet, $result);
    }
}
