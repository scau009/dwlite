<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Warehouse;
use App\Entity\Merchant;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WarehouseFactory
{
    private static int $counter = 0;

    public static function create(TestCase $test, array $overrides = []): Warehouse&MockObject
    {
        ++self::$counter;

        $warehouse = $test->createMock(Warehouse::class);
        $warehouse->method('getId')->willReturn($overrides['id'] ?? 'warehouse-'.self::$counter);
        $warehouse->method('getName')->willReturn($overrides['name'] ?? 'Test Warehouse '.self::$counter);
        $warehouse->method('getCode')->willReturn($overrides['code'] ?? 'WH'.str_pad((string)self::$counter, 3, '0', STR_PAD_LEFT));
        $warehouse->method('getType')->willReturn($overrides['type'] ?? Warehouse::TYPE_PLATFORM);
        $warehouse->method('getStatus')->willReturn($overrides['status'] ?? Warehouse::STATUS_ACTIVE);
        $warehouse->method('isPlatformWarehouse')->willReturn(($overrides['type'] ?? Warehouse::TYPE_PLATFORM) === Warehouse::TYPE_PLATFORM);
        $warehouse->method('isMerchantWarehouse')->willReturn(($overrides['type'] ?? Warehouse::TYPE_PLATFORM) === Warehouse::TYPE_MERCHANT);

        if (isset($overrides['merchant'])) {
            $warehouse->method('getMerchant')->willReturn($overrides['merchant']);
        } else {
            $warehouse->method('getMerchant')->willReturn(null);
        }

        return $warehouse;
    }

    public static function createPlatformWarehouse(TestCase $test, array $overrides = []): Warehouse&MockObject
    {
        $overrides['type'] = Warehouse::TYPE_PLATFORM;
        return self::create($test, $overrides);
    }

    public static function createMerchantWarehouse(TestCase $test, Merchant $merchant, array $overrides = []): Warehouse&MockObject
    {
        $overrides['type'] = Warehouse::TYPE_MERCHANT;
        $overrides['merchant'] = $merchant;
        return self::create($test, $overrides);
    }

    public static function reset(): void
    {
        self::$counter = 0;
    }
}
