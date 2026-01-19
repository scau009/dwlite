<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Wallet;
use App\Entity\Merchant;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WalletFactory
{
    private static int $counter = 0;

    public static function create(TestCase $test, array $overrides = []): Wallet&MockObject
    {
        ++self::$counter;

        $wallet = $test->createMock(Wallet::class);
        $wallet->method('getId')->willReturn($overrides['id'] ?? 'wallet-'.self::$counter);
        $wallet->method('getType')->willReturn($overrides['type'] ?? Wallet::TYPE_DEPOSIT);
        $wallet->method('getBalance')->willReturn($overrides['balance'] ?? '0.00');
        $wallet->method('getFrozenAmount')->willReturn($overrides['frozenAmount'] ?? '0.00');
        $wallet->method('getCurrency')->willReturn($overrides['currency'] ?? 'CNY');
        $wallet->method('getStatus')->willReturn($overrides['status'] ?? Wallet::STATUS_ACTIVE);

        if (isset($overrides['merchant'])) {
            $wallet->method('getMerchant')->willReturn($overrides['merchant']);
        }

        return $wallet;
    }

    public static function createDepositWallet(TestCase $test, array $overrides = []): Wallet&MockObject
    {
        $overrides['type'] = Wallet::TYPE_DEPOSIT;
        return self::create($test, $overrides);
    }

    public static function createBalanceWallet(TestCase $test, array $overrides = []): Wallet&MockObject
    {
        $overrides['type'] = Wallet::TYPE_BALANCE;
        return self::create($test, $overrides);
    }

    public static function reset(): void
    {
        self::$counter = 0;
    }
}
