<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Order;
use App\Entity\OrderSyncLog;
use App\Entity\SalesChannel;
use App\Repository\OrderRepository;
use App\Repository\OrderSyncLogRepository;
use App\Repository\OrderSyncStateRepository;
use App\Repository\ProductSkuRepository;
use App\Service\BusinessNoGenerator;
use App\Service\ChannelGateway\ChannelGatewayInterface;
use App\Service\ChannelGateway\ChannelGatewayRegistry;
use App\Service\ChannelGateway\Dto\Response\PulledOrderDto;
use App\Service\OrderSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class OrderSyncServiceTest extends TestCase
{
    private OrderRepository&MockObject $orderRepository;
    private OrderSyncLogRepository&MockObject $syncLogRepository;
    private OrderSyncStateRepository&MockObject $syncStateRepository;
    private ProductSkuRepository&MockObject $skuRepository;
    private ChannelGatewayRegistry&MockObject $gatewayRegistry;
    private BusinessNoGenerator&MockObject $businessNoGenerator;
    private EntityManagerInterface&MockObject $entityManager;
    private MessageBusInterface&MockObject $messageBus;
    private LoggerInterface&MockObject $logger;
    private OrderSyncService $service;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->syncLogRepository = $this->createMock(OrderSyncLogRepository::class);
        $this->syncStateRepository = $this->createMock(OrderSyncStateRepository::class);
        $this->skuRepository = $this->createMock(ProductSkuRepository::class);
        $this->gatewayRegistry = $this->createMock(ChannelGatewayRegistry::class);
        $this->businessNoGenerator = $this->createMock(BusinessNoGenerator::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new OrderSyncService(
            $this->orderRepository,
            $this->syncLogRepository,
            $this->syncStateRepository,
            $this->skuRepository,
            $this->gatewayRegistry,
            $this->businessNoGenerator,
            $this->entityManager,
            $this->messageBus,
            $this->logger,
        );
    }

    public function testPullOrders(): void
    {
        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getId')->willReturn('channel-123');
        $channel->method('getCode')->willReturn('kickscrew');

        $gateway = $this->createMock(ChannelGatewayInterface::class);

        $pulledOrder = new PulledOrderDto();
        $pulledOrder->externalOrderNo = 'EXT123';
        $pulledOrder->totalAmount = '100.00';
        $pulledOrder->currency = 'USD';

        $gateway->expects($this->once())
            ->method('pullOrders')
            ->willReturn([$pulledOrder]);

        $this->gatewayRegistry->expects($this->once())
            ->method('get')
            ->with('kickscrew')
            ->willReturn($gateway);

        $this->orderRepository->expects($this->once())
            ->method('findByExternalOrderNo')
            ->with('EXT123', $channel)
            ->willReturn(null);

        $this->businessNoGenerator->expects($this->once())
            ->method('generateOrderNo')
            ->willReturn('ORD20240115000001');

        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $startTime = new \DateTimeImmutable('-1 hour', new \DateTimeZone('UTC'));
        $endTime = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $result = $this->service->pullOrders($channel, $startTime, $endTime, 1, 50);

        $this->assertIsArray($result);
    }

    public function testPullOrdersSkipsDuplicates(): void
    {
        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getCode')->willReturn('kickscrew');

        $gateway = $this->createMock(ChannelGatewayInterface::class);

        $pulledOrder = new PulledOrderDto();
        $pulledOrder->externalOrderNo = 'EXT123';

        $gateway->expects($this->once())
            ->method('pullOrders')
            ->willReturn([$pulledOrder]);

        $this->gatewayRegistry->expects($this->once())
            ->method('get')
            ->willReturn($gateway);

        $existingOrder = $this->createMock(Order::class);
        $this->orderRepository->expects($this->once())
            ->method('findByExternalOrderNo')
            ->willReturn($existingOrder);

        // Should not create a new order
        $this->businessNoGenerator->expects($this->never())
            ->method('generateOrderNo');

        $startTime = new \DateTimeImmutable('-1 hour', new \DateTimeZone('UTC'));
        $endTime = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $this->service->pullOrders($channel, $startTime, $endTime, 1, 50);
    }

    public function testConfirmOrder(): void
    {
        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getCode')->willReturn('kickscrew');

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');
        $order->method('getSalesChannel')->willReturn($channel);
        $order->method('getExternalOrderNo')->willReturn('EXT123');

        $gateway = $this->createMock(ChannelGatewayInterface::class);
        $gateway->expects($this->once())
            ->method('confirmOrder')
            ->willReturn(true);

        $this->gatewayRegistry->expects($this->once())
            ->method('get')
            ->with('kickscrew')
            ->willReturn($gateway);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(OrderSyncLog::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->confirmOrder($order);

        $this->assertInstanceOf(OrderSyncLog::class, $result);
    }

    public function testShipOrder(): void
    {
        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getCode')->willReturn('kickscrew');

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');
        $order->method('getSalesChannel')->willReturn($channel);
        $order->method('getExternalOrderNo')->willReturn('EXT123');
        $order->method('getCarrierCode')->willReturn('SF');
        $order->method('getTrackingNumber')->willReturn('SF123456789');

        $gateway = $this->createMock(ChannelGatewayInterface::class);
        $gateway->expects($this->once())
            ->method('shipOrder')
            ->willReturn(true);

        $this->gatewayRegistry->expects($this->once())
            ->method('get')
            ->willReturn($gateway);

        $result = $this->service->shipOrder($order);

        $this->assertInstanceOf(OrderSyncLog::class, $result);
    }
}
