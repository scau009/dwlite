<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\InboundOrder;
use App\Entity\InboundOrderItem;
use App\Entity\InboundException;
use App\Entity\Merchant;
use App\Entity\Warehouse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class InboundOrderFactory
{
    private static int $counter = 0;

    public static function create(TestCase $test, array $overrides = []): InboundOrder&MockObject
    {
        ++self::$counter;

        $order = $test->createMock(InboundOrder::class);
        $order->method('getId')->willReturn($overrides['id'] ?? 'inbound-'.self::$counter);
        $order->method('getOrderNo')->willReturn($overrides['orderNo'] ?? 'IB'.date('Ymd').str_pad((string)self::$counter, 6, '0', STR_PAD_LEFT));
        $order->method('getStatus')->willReturn($overrides['status'] ?? InboundOrder::STATUS_DRAFT);
        $order->method('isDraft')->willReturn(($overrides['status'] ?? InboundOrder::STATUS_DRAFT) === InboundOrder::STATUS_DRAFT);
        $order->method('isShipped')->willReturn(($overrides['status'] ?? InboundOrder::STATUS_DRAFT) === InboundOrder::STATUS_SHIPPED);
        $order->method('getTotalExpectedQuantity')->willReturn($overrides['expectedQuantity'] ?? 0);
        $order->method('getTotalReceivedQuantity')->willReturn($overrides['receivedQuantity'] ?? 0);
        $order->method('getMerchantNotes')->willReturn($overrides['merchantNotes'] ?? null);

        if (isset($overrides['merchant'])) {
            $order->method('getMerchant')->willReturn($overrides['merchant']);
        }

        if (isset($overrides['warehouse'])) {
            $order->method('getWarehouse')->willReturn($overrides['warehouse']);
        }

        return $order;
    }

    public static function createDraft(TestCase $test, array $overrides = []): InboundOrder&MockObject
    {
        $overrides['status'] = InboundOrder::STATUS_DRAFT;
        return self::create($test, $overrides);
    }

    public static function createShipped(TestCase $test, array $overrides = []): InboundOrder&MockObject
    {
        $overrides['status'] = InboundOrder::STATUS_SHIPPED;
        return self::create($test, $overrides);
    }

    public static function reset(): void
    {
        self::$counter = 0;
    }
}

class InboundOrderItemFactory
{
    private static int $counter = 0;

    public static function create(TestCase $test, array $overrides = []): InboundOrderItem&MockObject
    {
        ++self::$counter;

        $item = $test->createMock(InboundOrderItem::class);
        $item->method('getId')->willReturn($overrides['id'] ?? 'inbound-item-'.self::$counter);
        $item->method('getExpectedQuantity')->willReturn($overrides['expectedQuantity'] ?? 10);
        $item->method('getReceivedQuantity')->willReturn($overrides['receivedQuantity'] ?? 0);
        $item->method('getDamagedQuantity')->willReturn($overrides['damagedQuantity'] ?? 0);
        $item->method('getUnitCost')->willReturn($overrides['unitCost'] ?? '50.00');
        $item->method('getStatus')->willReturn($overrides['status'] ?? InboundOrderItem::STATUS_PENDING);
        $item->method('getStyleNumber')->willReturn($overrides['styleNumber'] ?? 'STYLE001');
        $item->method('getSkuName')->willReturn($overrides['skuName'] ?? 'US 10');

        if (isset($overrides['inboundOrder'])) {
            $item->method('getInboundOrder')->willReturn($overrides['inboundOrder']);
        }

        if (isset($overrides['productSku'])) {
            $item->method('getProductSku')->willReturn($overrides['productSku']);
        }

        return $item;
    }

    public static function reset(): void
    {
        self::$counter = 0;
    }
}
