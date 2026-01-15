<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Fulfillment;
use App\Entity\FulfillmentItem;
use App\Entity\Order;
use App\Entity\Warehouse;
use App\Entity\Merchant;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FulfillmentFactory
{
    private static int $counter = 0;

    public static function create(TestCase $test, array $overrides = []): Fulfillment&MockObject
    {
        ++self::$counter;

        $fulfillment = $test->createMock(Fulfillment::class);
        $fulfillment->method('getId')->willReturn($overrides['id'] ?? 'fulfillment-'.self::$counter);
        $fulfillment->method('getFulfillmentNo')->willReturn($overrides['fulfillmentNo'] ?? 'FF'.date('Ymd').str_pad((string)self::$counter, 6, '0', STR_PAD_LEFT));
        $fulfillment->method('getType')->willReturn($overrides['type'] ?? Fulfillment::TYPE_PLATFORM_WAREHOUSE);
        $fulfillment->method('getStatus')->willReturn($overrides['status'] ?? Fulfillment::STATUS_PENDING);
        $fulfillment->method('isPlatformWarehouse')->willReturn(($overrides['type'] ?? Fulfillment::TYPE_PLATFORM_WAREHOUSE) === Fulfillment::TYPE_PLATFORM_WAREHOUSE);
        $fulfillment->method('isMerchantWarehouse')->willReturn(($overrides['type'] ?? Fulfillment::TYPE_PLATFORM_WAREHOUSE) === Fulfillment::TYPE_MERCHANT_WAREHOUSE);
        $fulfillment->method('isPending')->willReturn(($overrides['status'] ?? Fulfillment::STATUS_PENDING) === Fulfillment::STATUS_PENDING);

        if (isset($overrides['order'])) {
            $fulfillment->method('getOrder')->willReturn($overrides['order']);
        }

        if (isset($overrides['warehouse'])) {
            $fulfillment->method('getWarehouse')->willReturn($overrides['warehouse']);
        }

        if (isset($overrides['merchant'])) {
            $fulfillment->method('getMerchant')->willReturn($overrides['merchant']);
        }

        if (isset($overrides['items'])) {
            $fulfillment->method('getItems')->willReturn(new ArrayCollection($overrides['items']));
        } else {
            $fulfillment->method('getItems')->willReturn(new ArrayCollection());
        }

        return $fulfillment;
    }

    public static function createPlatformWarehouse(TestCase $test, array $overrides = []): Fulfillment&MockObject
    {
        $overrides['type'] = Fulfillment::TYPE_PLATFORM_WAREHOUSE;
        return self::create($test, $overrides);
    }

    public static function createMerchantWarehouse(TestCase $test, array $overrides = []): Fulfillment&MockObject
    {
        $overrides['type'] = Fulfillment::TYPE_MERCHANT_WAREHOUSE;
        return self::create($test, $overrides);
    }

    public static function reset(): void
    {
        self::$counter = 0;
    }
}

class FulfillmentItemFactory
{
    private static int $counter = 0;

    public static function create(TestCase $test, array $overrides = []): FulfillmentItem&MockObject
    {
        ++self::$counter;

        $item = $test->createMock(FulfillmentItem::class);
        $item->method('getId')->willReturn($overrides['id'] ?? 'fulfillment-item-'.self::$counter);
        $item->method('getQuantity')->willReturn($overrides['quantity'] ?? 1);
        $item->method('getUnitPrice')->willReturn($overrides['unitPrice'] ?? '100.00');
        $item->method('getCommission')->willReturn($overrides['commission'] ?? '10.00');

        if (isset($overrides['fulfillment'])) {
            $item->method('getFulfillment')->willReturn($overrides['fulfillment']);
        }

        if (isset($overrides['orderItem'])) {
            $item->method('getOrderItem')->willReturn($overrides['orderItem']);
        }

        if (isset($overrides['merchant'])) {
            $item->method('getMerchant')->willReturn($overrides['merchant']);
        }

        return $item;
    }

    public static function reset(): void
    {
        self::$counter = 0;
    }
}
