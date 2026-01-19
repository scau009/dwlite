<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Merchant;
use App\Entity\MerchantBankAccount;
use App\Entity\Payout;
use App\Entity\Wallet;
use App\Repository\MerchantBankAccountRepository;
use App\Repository\PayoutRepository;
use App\Repository\WalletRepository;
use App\Service\BusinessNoGenerator;
use App\Service\PayoutService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PayoutServiceTest extends TestCase
{
    private PayoutRepository&MockObject $payoutRepository;
    private WalletRepository&MockObject $walletRepository;
    private MerchantBankAccountRepository&MockObject $bankAccountRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private BusinessNoGenerator&MockObject $businessNoGenerator;
    private TranslatorInterface&MockObject $translator;
    private LoggerInterface&MockObject $logger;
    private PayoutService $service;

    protected function setUp(): void
    {
        $this->payoutRepository = $this->createMock(PayoutRepository::class);
        $this->walletRepository = $this->createMock(WalletRepository::class);
        $this->bankAccountRepository = $this->createMock(MerchantBankAccountRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->businessNoGenerator = $this->createMock(BusinessNoGenerator::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->translator->method('trans')->willReturnCallback(fn(string $key) => $key);

        $this->service = new PayoutService(
            $this->payoutRepository,
            $this->walletRepository,
            $this->bankAccountRepository,
            $this->entityManager,
            $this->businessNoGenerator,
            $this->translator,
            $this->logger,
        );
    }

    public function testCreatePayout(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $bankAccount = $this->createMock(MerchantBankAccount::class);
        $bankAccount->method('getId')->willReturn('bank-account-123');
        $bankAccount->method('getMerchant')->willReturn($merchant);
        $bankAccount->method('getBankName')->willReturn('Test Bank');
        $bankAccount->method('getAccountNumber')->willReturn('1234567890');
        $bankAccount->method('getAccountHolder')->willReturn('Test Holder');

        $wallet = $this->createMock(Wallet::class);
        $wallet->method('getBalance')->willReturn('1000.00');
        $wallet->method('getAvailableBalance')->willReturn('1000.00');

        $this->bankAccountRepository->expects($this->once())
            ->method('find')
            ->with('bank-account-123')
            ->willReturn($bankAccount);

        $this->walletRepository->expects($this->once())
            ->method('findBalanceWallet')
            ->with($merchant)
            ->willReturn($wallet);

        $wallet->expects($this->once())
            ->method('freeze')
            ->with('100.00');

        $this->businessNoGenerator->expects($this->once())
            ->method('generatePayoutNo')
            ->willReturn('PO20240115000001');

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Payout::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->createPayout($merchant, 'bank-account-123', '100.00', 'Test payout');

        $this->assertInstanceOf(Payout::class, $result);
    }

    public function testCreatePayoutWithInsufficientBalance(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $bankAccount = $this->createMock(MerchantBankAccount::class);
        $bankAccount->method('getMerchant')->willReturn($merchant);

        $wallet = $this->createMock(Wallet::class);
        $wallet->method('getBalance')->willReturn('50.00');
        $wallet->method('getAvailableBalance')->willReturn('50.00');

        $this->bankAccountRepository->expects($this->once())
            ->method('find')
            ->with('bank-account-123')
            ->willReturn($bankAccount);

        $this->walletRepository->expects($this->once())
            ->method('findBalanceWallet')
            ->with($merchant)
            ->willReturn($wallet);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('payout.insufficient_balance');

        $this->service->createPayout($merchant, 'bank-account-123', '100.00');
    }

    public function testCreatePayoutWithInvalidBankAccount(): void
    {
        $merchant = $this->createMock(Merchant::class);

        $this->bankAccountRepository->expects($this->once())
            ->method('find')
            ->with('invalid-bank-account')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('payout.bank_account_not_found');

        $this->service->createPayout($merchant, 'invalid-bank-account', '100.00');
    }

    public function testCreatePayoutWithWrongMerchantBankAccount(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $otherMerchant = $this->createMock(Merchant::class);
        $otherMerchant->method('getId')->willReturn('merchant-456');

        $bankAccount = $this->createMock(MerchantBankAccount::class);
        $bankAccount->method('getMerchant')->willReturn($otherMerchant);

        $this->bankAccountRepository->expects($this->once())
            ->method('find')
            ->with('bank-account-123')
            ->willReturn($bankAccount);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('payout.bank_account_not_owned');

        $this->service->createPayout($merchant, 'bank-account-123', '100.00');
    }

    public function testCreatePayoutWithNoBalanceWallet(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $bankAccount = $this->createMock(MerchantBankAccount::class);
        $bankAccount->method('getMerchant')->willReturn($merchant);

        $this->bankAccountRepository->expects($this->once())
            ->method('find')
            ->with('bank-account-123')
            ->willReturn($bankAccount);

        $this->walletRepository->expects($this->once())
            ->method('findBalanceWallet')
            ->with($merchant)
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('payout.wallet_not_found');

        $this->service->createPayout($merchant, 'bank-account-123', '100.00');
    }

    public function testCreatePayoutFreezesFunds(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $bankAccount = $this->createMock(MerchantBankAccount::class);
        $bankAccount->method('getMerchant')->willReturn($merchant);
        $bankAccount->method('getBankName')->willReturn('Test Bank');
        $bankAccount->method('getAccountNumber')->willReturn('1234567890');
        $bankAccount->method('getAccountHolder')->willReturn('Test Holder');

        $wallet = $this->createMock(Wallet::class);
        $wallet->method('getBalance')->willReturn('1000.00');
        $wallet->method('getAvailableBalance')->willReturn('1000.00');

        $this->bankAccountRepository->method('find')->willReturn($bankAccount);
        $this->walletRepository->method('findBalanceWallet')->willReturn($wallet);
        $this->businessNoGenerator->method('generatePayoutNo')->willReturn('PO001');

        // Verify that freeze is called with the correct amount
        $wallet->expects($this->once())
            ->method('freeze')
            ->with('200.50');

        $this->service->createPayout($merchant, 'bank-account-123', '200.50');
    }
}
