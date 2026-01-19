<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Merchant;
use App\Entity\User;
use App\Entity\Wallet;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MerchantFactory
{
    private static int $counter = 0;

    public static function create(TestCase $test, array $overrides = []): Merchant&MockObject
    {
        ++self::$counter;

        $merchant = $test->createMock(Merchant::class);
        $merchant->method('getId')->willReturn($overrides['id'] ?? 'merchant-'.self::$counter);
        $merchant->method('getStatus')->willReturn($overrides['status'] ?? Merchant::STATUS_APPROVED);
        $merchant->method('getCompanyName')->willReturn($overrides['companyName'] ?? 'Test Company '.self::$counter);
        $merchant->method('getContactName')->willReturn($overrides['contactName'] ?? 'Contact '.self::$counter);
        $merchant->method('getContactPhone')->willReturn($overrides['contactPhone'] ?? '1380000'.str_pad((string)self::$counter, 4, '0', STR_PAD_LEFT));

        if (isset($overrides['user'])) {
            $merchant->method('getUser')->willReturn($overrides['user']);
        }

        return $merchant;
    }

    public static function createWithUser(TestCase $test, array $merchantOverrides = [], array $userOverrides = []): Merchant&MockObject
    {
        $user = UserFactory::createMerchantUser($test, $userOverrides);
        $merchantOverrides['user'] = $user;
        return self::create($test, $merchantOverrides);
    }

    public static function createPending(TestCase $test, array $overrides = []): Merchant&MockObject
    {
        $overrides['status'] = Merchant::STATUS_PENDING;
        return self::create($test, $overrides);
    }

    public static function createApproved(TestCase $test, array $overrides = []): Merchant&MockObject
    {
        $overrides['status'] = Merchant::STATUS_APPROVED;
        return self::create($test, $overrides);
    }

    public static function reset(): void
    {
        self::$counter = 0;
    }
}
