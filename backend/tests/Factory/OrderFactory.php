<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\SalesChannel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderFactory
{
    private static int $counter = 0;

    public static function create(TestCase $test, array $overrides = []): Order&MockObject
    {
        ++self::$counter;

        $order = $test->createMock(Order::class);
        $order->method('getId')->willReturn($overrides['id'] ?? 'order-'.self::$counter);
        $order->method('getOrderNo')->willReturn($overrides['orderNo'] ?? 'PO'.date('Ymd').str_pad((string)self::$counter, 5, '0', STR_PAD_LEFT));
        $order->method('getExternalOrderNo')->willReturn($overrides['externalOrderNo'] ?? 'EXT'.self::$counter);
        $order->method('getStatus')->willReturn($overrides['status'] ?? Order::STATUS_PENDING);
        $order->method('getTotalAmount')->willReturn($overrides['totalAmount'] ?? '100.00');
        $order->method('getCurrency')->willReturn($overrides['currency'] ?? 'USD');
        $order->method('getReceiverName')->willReturn($overrides['receiverName'] ?? 'John Doe');
        $order->method('getReceiverPhone')->willReturn($overrides['receiverPhone'] ?? '1234567890');
        $order->method('getReceiverFullAddress')->willReturn($overrides['receiverAddress'] ?? '123 Main St');
        $order->method('getReceiverPostalCode')->willReturn($overrides['postalCode'] ?? '12345');
        $order->method('canAllocate')->willReturn($overrides['canAllocate'] ?? true);
        $order->method('isPending')->willReturn(($overrides['status'] ?? Order::STATUS_PENDING) === Order::STATUS_PENDING);

        if (isset($overrides['salesChannel'])) {
            $order->method('getSalesChannel')->willReturn($overrides['salesChannel']);
        }

        return $order;
    }

    public static function createPending(TestCase $test, array $overrides = []): Order&MockObject
    {
        $overrides['status'] = Order::STATUS_PENDING;
        return self::create($test, $overrides);
    }

    public static function createAllocating(TestCase $test, array $overrides = []): Order&MockObject
    {
        $overrides['status'] = Order::STATUS_ALLOCATING;
        return self::create($test, $overrides);
    }

    public static function reset(): void
    {
        self::$counter = 0;
    }
}

class OrderItemFactory
{
    private static int $counter = 0;

    public static function create(TestCase $test, array $overrides = []): OrderItem&MockObject
    {
        ++self::$counter;

        $item = $test->createMock(OrderItem::class);
        $item->method('getId')->willReturn($overrides['id'] ?? 'order-item-'.self::$counter);
        $item->method('getQuantity')->willReturn($overrides['quantity'] ?? 1);
        $item->method('getUnitPrice')->willReturn($overrides['unitPrice'] ?? '100.00');
        $item->method('getTotalPrice')->willReturn($overrides['totalPrice'] ?? '100.00');

        if (isset($overrides['order'])) {
            $item->method('getOrder')->willReturn($overrides['order']);
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

class SalesChannelFactory
{
    private static int $counter = 0;

    public static function create(TestCase $test, array $overrides = []): SalesChannel&MockObject
    {
        ++self::$counter;

        $channel = $test->createMock(SalesChannel::class);
        $channel->method('getId')->willReturn($overrides['id'] ?? 'channel-'.self::$counter);
        $channel->method('getName')->willReturn($overrides['name'] ?? 'Channel '.self::$counter);
        $channel->method('getCode')->willReturn($overrides['code'] ?? 'CH'.str_pad((string)self::$counter, 3, '0', STR_PAD_LEFT));
        $channel->method('getStatus')->willReturn($overrides['status'] ?? SalesChannel::STATUS_ACTIVE);

        return $channel;
    }

    public static function reset(): void
    {
        self::$counter = 0;
    }
}
