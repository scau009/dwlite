<?php

declare(strict_types=1);

namespace App\Tests\Service\Fulfillment;

use App\Entity\Fulfillment;
use App\Entity\FulfillmentItem;
use App\Entity\Merchant;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\OutboundOrder;
use App\Entity\Product;
use App\Entity\ProductSku;
use App\Entity\Warehouse;
use App\Service\Fulfillment\OutboundOrderCreationService;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OutboundOrderCreationServiceTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private OutboundOrderCreationService $service;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new OutboundOrderCreationService($this->logger);
    }

    public function testCreateFromFulfillmentSuccess(): void
    {
        // Create mocks
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $warehouse = $this->createMock(Warehouse::class);
        $warehouse->method('getId')->willReturn('warehouse-123');

        $product = $this->createMock(Product::class);
        $product->method('getStyleNumber')->willReturn('STYLE001');
        $product->method('getColor')->willReturn('Black');
        $product->method('getName')->willReturn('Test Product');
        $product->method('getPrimaryImage')->willReturn(null);

        $sku = $this->createMock(ProductSku::class);
        $sku->method('getSizeValue')->willReturn('US 10');
        $sku->method('getProduct')->willReturn($product);

        $orderItem = $this->createMock(OrderItem::class);
        $orderItem->method('getProductSku')->willReturn($sku);

        $fulfillmentItem = $this->createMock(FulfillmentItem::class);
        $fulfillmentItem->method('getMerchant')->willReturn($merchant);
        $fulfillmentItem->method('getQuantity')->willReturn(2);
        $fulfillmentItem->method('getOrderItem')->willReturn($orderItem);

        $order = $this->createMock(Order::class);
        $order->method('getReceiverName')->willReturn('John Doe');
        $order->method('getReceiverPhone')->willReturn('1234567890');
        $order->method('getReceiverFullAddress')->willReturn('123 Main St, City, Country');
        $order->method('getReceiverPostalCode')->willReturn('12345');

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getId')->willReturn('fulfillment-123');
        $fulfillment->method('getFulfillmentNo')->willReturn('FF20250114000001');
        $fulfillment->method('isPlatformWarehouse')->willReturn(true);
        $fulfillment->method('getWarehouse')->willReturn($warehouse);
        $fulfillment->method('getOrder')->willReturn($order);
        $fulfillment->method('getItems')->willReturn(new ArrayCollection([$fulfillmentItem]));

        // Execute
        $outboundOrder = $this->service->createFromFulfillment($fulfillment);

        // Assert
        $this->assertInstanceOf(OutboundOrder::class, $outboundOrder);
        $this->assertSame($warehouse, $outboundOrder->getWarehouse());
        $this->assertSame($merchant, $outboundOrder->getMerchant());
        $this->assertEquals('John Doe', $outboundOrder->getReceiverName());
        $this->assertEquals('1234567890', $outboundOrder->getReceiverPhone());
        $this->assertEquals('123 Main St, City, Country', $outboundOrder->getReceiverAddress());
        $this->assertEquals(OutboundOrder::STATUS_PENDING, $outboundOrder->getStatus());
        $this->assertCount(1, $outboundOrder->getItems());
    }

    public function testThrowsExceptionForMerchantWarehouse(): void
    {
        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('isPlatformWarehouse')->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('只有寄售履约单可以创建出库单');

        $this->service->createFromFulfillment($fulfillment);
    }

    public function testThrowsExceptionForEmptyItems(): void
    {
        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('isPlatformWarehouse')->willReturn(true);
        $fulfillment->method('getItems')->willReturn(new ArrayCollection());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('履约单没有明细项');

        $this->service->createFromFulfillment($fulfillment);
    }

    public function testThrowsExceptionWhenMerchantMissing(): void
    {
        $fulfillmentItem = $this->createMock(FulfillmentItem::class);
        $fulfillmentItem->method('getMerchant')->willReturn(null);

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('isPlatformWarehouse')->willReturn(true);
        $fulfillment->method('getItems')->willReturn(new ArrayCollection([$fulfillmentItem]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('履约单明细缺少商户信息');

        $this->service->createFromFulfillment($fulfillment);
    }
}
