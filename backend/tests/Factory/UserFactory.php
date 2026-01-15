<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UserFactory
{
    private static int $counter = 0;

    public static function create(TestCase $test, array $overrides = []): User&MockObject
    {
        ++self::$counter;

        $user = $test->createMock(User::class);
        $user->method('getId')->willReturn($overrides['id'] ?? 'user-'.self::$counter);
        $user->method('getEmail')->willReturn($overrides['email'] ?? 'user'.self::$counter.'@example.com');
        $user->method('getRoles')->willReturn($overrides['roles'] ?? ['ROLE_USER']);
        $user->method('getAccountType')->willReturn($overrides['accountType'] ?? User::ACCOUNT_TYPE_MERCHANT);
        $user->method('isVerified')->willReturn($overrides['verified'] ?? true);

        return $user;
    }

    public static function createAdmin(TestCase $test, array $overrides = []): User&MockObject
    {
        $overrides['roles'] = ['ROLE_ADMIN'];
        $overrides['accountType'] = User::ACCOUNT_TYPE_ADMIN;
        return self::create($test, $overrides);
    }

    public static function createMerchantUser(TestCase $test, array $overrides = []): User&MockObject
    {
        $overrides['accountType'] = User::ACCOUNT_TYPE_MERCHANT;
        return self::create($test, $overrides);
    }

    public static function createWarehouseUser(TestCase $test, array $overrides = []): User&MockObject
    {
        $overrides['accountType'] = User::ACCOUNT_TYPE_WAREHOUSE;
        return self::create($test, $overrides);
    }

    public static function reset(): void
    {
        self::$counter = 0;
    }
}
