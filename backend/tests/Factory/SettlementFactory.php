<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Settlement;
use App\Entity\SettlementItem;
use App\Entity\Payout;
use App\Entity\Merchant;
use App\Entity\MerchantBankAccount;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SettlementFactory
{
    private static int $counter = 0;

    public static function create(TestCase $test, array $overrides = []): Settlement&MockObject
    {
        ++self::$counter;

        $settlement = $test->createMock(Settlement::class);
        $settlement->method('getId')->willReturn($overrides['id'] ?? 'settlement-'.self::$counter);
        $settlement->method('getSettlementNo')->willReturn($overrides['settlementNo'] ?? 'ST'.date('Ymd').str_pad((string)self::$counter, 6, '0', STR_PAD_LEFT));
        $settlement->method('getStatus')->willReturn($overrides['status'] ?? Settlement::STATUS_PENDING);
        $settlement->method('getTotalAmount')->willReturn($overrides['totalAmount'] ?? '100.00');
        $settlement->method('getCommission')->willReturn($overrides['commission'] ?? '10.00');
        $settlement->method('getNetAmount')->willReturn($overrides['netAmount'] ?? '90.00');
        $settlement->method('getCurrency')->willReturn($overrides['currency'] ?? 'USD');
        $settlement->method('isPending')->willReturn(($overrides['status'] ?? Settlement::STATUS_PENDING) === Settlement::STATUS_PENDING);

        if (isset($overrides['merchant'])) {
            $settlement->method('getMerchant')->willReturn($overrides['merchant']);
        }

        if (isset($overrides['fulfillment'])) {
            $settlement->method('getFulfillment')->willReturn($overrides['fulfillment']);
        }

        return $settlement;
    }

    public static function reset(): void
    {
        self::$counter = 0;
    }
}

class PayoutFactory
{
    private static int $counter = 0;

    public static function create(TestCase $test, array $overrides = []): Payout&MockObject
    {
        ++self::$counter;

        $payout = $test->createMock(Payout::class);
        $payout->method('getId')->willReturn($overrides['id'] ?? 'payout-'.self::$counter);
        $payout->method('getPayoutNo')->willReturn($overrides['payoutNo'] ?? 'PO'.date('Ymd').str_pad((string)self::$counter, 6, '0', STR_PAD_LEFT));
        $payout->method('getStatus')->willReturn($overrides['status'] ?? Payout::STATUS_PENDING);
        $payout->method('getAmount')->willReturn($overrides['amount'] ?? '100.00');
        $payout->method('getCurrency')->willReturn($overrides['currency'] ?? 'CNY');
        $payout->method('isPending')->willReturn(($overrides['status'] ?? Payout::STATUS_PENDING) === Payout::STATUS_PENDING);

        if (isset($overrides['merchant'])) {
            $payout->method('getMerchant')->willReturn($overrides['merchant']);
        }

        if (isset($overrides['bankAccount'])) {
            $payout->method('getBankAccount')->willReturn($overrides['bankAccount']);
        }

        return $payout;
    }

    public static function reset(): void
    {
        self::$counter = 0;
    }
}

class MerchantBankAccountFactory
{
    private static int $counter = 0;

    public static function create(TestCase $test, array $overrides = []): MerchantBankAccount&MockObject
    {
        ++self::$counter;

        $account = $test->createMock(MerchantBankAccount::class);
        $account->method('getId')->willReturn($overrides['id'] ?? 'bank-account-'.self::$counter);
        $account->method('getBankName')->willReturn($overrides['bankName'] ?? 'Test Bank');
        $account->method('getAccountNumber')->willReturn($overrides['accountNumber'] ?? '1234567890'.self::$counter);
        $account->method('getAccountHolder')->willReturn($overrides['accountHolder'] ?? 'Test Holder');
        $account->method('isDefault')->willReturn($overrides['isDefault'] ?? false);

        if (isset($overrides['merchant'])) {
            $account->method('getMerchant')->willReturn($overrides['merchant']);
        }

        return $account;
    }

    public static function reset(): void
    {
        self::$counter = 0;
    }
}
