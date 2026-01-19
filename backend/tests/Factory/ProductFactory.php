<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Product;
use App\Entity\ProductSku;
use App\Entity\Brand;
use App\Entity\Category;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProductFactory
{
    private static int $counter = 0;

    public static function create(TestCase $test, array $overrides = []): Product&MockObject
    {
        ++self::$counter;

        $product = $test->createMock(Product::class);
        $product->method('getId')->willReturn($overrides['id'] ?? 'P'.str_pad((string) self::$counter, 8, '0', STR_PAD_LEFT));
        $product->method('getName')->willReturn($overrides['name'] ?? 'Test Product '.self::$counter);
        $product->method('getStyleNumber')->willReturn($overrides['styleNumber'] ?? 'STYLE'.str_pad((string)self::$counter, 3, '0', STR_PAD_LEFT));
        $product->method('getColor')->willReturn($overrides['color'] ?? 'Black');
        $product->method('getStatus')->willReturn($overrides['status'] ?? Product::STATUS_ACTIVE);
        $product->method('getPrimaryImage')->willReturn($overrides['primaryImage'] ?? null);

        if (isset($overrides['brand'])) {
            $product->method('getBrand')->willReturn($overrides['brand']);
        }

        if (isset($overrides['category'])) {
            $product->method('getCategory')->willReturn($overrides['category']);
        }

        return $product;
    }

    public static function reset(): void
    {
        self::$counter = 0;
    }
}

class ProductSkuFactory
{
    private static int $counter = 0;

    public static function create(TestCase $test, array $overrides = []): ProductSku&MockObject
    {
        ++self::$counter;

        $sku = $test->createMock(ProductSku::class);
        $sku->method('getId')->willReturn($overrides['id'] ?? 'S'.str_pad((string) self::$counter, 8, '0', STR_PAD_LEFT));
        $sku->method('getSizeValue')->willReturn($overrides['sizeValue'] ?? 'US '.self::$counter);
        $sku->method('getSizeUnit')->willReturn($overrides['sizeUnit'] ?? 'US');
        $sku->method('getPrice')->willReturn($overrides['price'] ?? '100.00');
        $sku->method('getCurrency')->willReturn($overrides['currency'] ?? 'USD');
        $sku->method('getBarcode')->willReturn($overrides['barcode'] ?? null);

        if (isset($overrides['product'])) {
            $sku->method('getProduct')->willReturn($overrides['product']);
        }

        return $sku;
    }

    public static function createWithProduct(TestCase $test, array $skuOverrides = [], array $productOverrides = []): ProductSku&MockObject
    {
        $product = ProductFactory::create($test, $productOverrides);
        $skuOverrides['product'] = $product;
        return self::create($test, $skuOverrides);
    }

    public static function reset(): void
    {
        self::$counter = 0;
    }
}

class BrandFactory
{
    private static int $counter = 0;

    public static function create(TestCase $test, array $overrides = []): Brand&MockObject
    {
        ++self::$counter;

        $brand = $test->createMock(Brand::class);
        $brand->method('getId')->willReturn($overrides['id'] ?? 'brand-'.self::$counter);
        $brand->method('getName')->willReturn($overrides['name'] ?? 'Brand '.self::$counter);
        $brand->method('getStatus')->willReturn($overrides['status'] ?? Brand::STATUS_ACTIVE);

        return $brand;
    }

    public static function reset(): void
    {
        self::$counter = 0;
    }
}

class CategoryFactory
{
    private static int $counter = 0;

    public static function create(TestCase $test, array $overrides = []): Category&MockObject
    {
        ++self::$counter;

        $category = $test->createMock(Category::class);
        $category->method('getId')->willReturn($overrides['id'] ?? 'category-'.self::$counter);
        $category->method('getName')->willReturn($overrides['name'] ?? 'Category '.self::$counter);
        $category->method('getStatus')->willReturn($overrides['status'] ?? Category::STATUS_ACTIVE);

        return $category;
    }

    public static function reset(): void
    {
        self::$counter = 0;
    }
}
