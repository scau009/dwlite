<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\MerchantInventory;
use App\Entity\InventoryListing;
use App\Entity\Merchant;
use App\Entity\Warehouse;
use App\Entity\ProductSku;
use App\Entity\SalesChannel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class InventoryFactory
{
    private static int $counter = 0;

    public static function create(TestCase $test, array $overrides = []): MerchantInventory&MockObject
    {
        ++self::$counter;

        $inventory = $test->createMock(MerchantInventory::class);
        $inventory->method('getId')->willReturn($overrides['id'] ?? 'inventory-'.self::$counter);
        $inventory->method('getQuantityAvailable')->willReturn($overrides['available'] ?? 0);
        $inventory->method('getQuantityInTransit')->willReturn($overrides['inTransit'] ?? 0);
        $inventory->method('getQuantityReserved')->willReturn($overrides['reserved'] ?? 0);
        $inventory->method('getQuantityDamaged')->willReturn($overrides['damaged'] ?? 0);
        $inventory->method('getAverageCost')->willReturn($overrides['averageCost'] ?? '50.00');
        $inventory->method('getCurrency')->willReturn($overrides['currency'] ?? 'USD');
        $inventory->method('isMerchantOwned')->willReturn($overrides['isMerchantOwned'] ?? false);

        if (isset($overrides['merchant'])) {
            $inventory->method('getMerchant')->willReturn($overrides['merchant']);
        }

        if (isset($overrides['warehouse'])) {
            $inventory->method('getWarehouse')->willReturn($overrides['warehouse']);
        }

        if (isset($overrides['productSku'])) {
            $inventory->method('getProductSku')->willReturn($overrides['productSku']);
        }

        return $inventory;
    }

    public static function createWithStock(TestCase $test, int $available, array $overrides = []): MerchantInventory&MockObject
    {
        $overrides['available'] = $available;
        return self::create($test, $overrides);
    }

    public static function reset(): void
    {
        self::$counter = 0;
    }
}

class InventoryListingFactory
{
    private static int $counter = 0;

    public static function create(TestCase $test, array $overrides = []): InventoryListing&MockObject
    {
        ++self::$counter;

        $listing = $test->createMock(InventoryListing::class);
        $listing->method('getId')->willReturn($overrides['id'] ?? 'listing-'.self::$counter);
        $listing->method('getStatus')->willReturn($overrides['status'] ?? InventoryListing::STATUS_ACTIVE);
        $listing->method('getFulfillmentMode')->willReturn($overrides['fulfillmentMode'] ?? InventoryListing::FULFILLMENT_CONSIGNMENT);
        $listing->method('getPrice')->willReturn($overrides['price'] ?? '100.00');
        $listing->method('getCurrency')->willReturn($overrides['currency'] ?? 'USD');
        $listing->method('isActive')->willReturn(($overrides['status'] ?? InventoryListing::STATUS_ACTIVE) === InventoryListing::STATUS_ACTIVE);

        if (isset($overrides['inventory'])) {
            $listing->method('getMerchantInventory')->willReturn($overrides['inventory']);
        }

        if (isset($overrides['salesChannel'])) {
            $listing->method('getSalesChannel')->willReturn($overrides['salesChannel']);
        }

        return $listing;
    }

    public static function reset(): void
    {
        self::$counter = 0;
    }
}
