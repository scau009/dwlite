<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Order;
use App\Entity\OrderSyncLog;
use App\Entity\OrderSyncState;
use App\Entity\SalesChannel;
use App\Repository\ChannelProductRepository;
use App\Repository\OrderRepository;
use App\Repository\OrderSyncStateRepository;
use App\Service\BusinessNoGenerator;
use App\Service\ChannelGateway\ChannelGatewayInterface;
use App\Service\ChannelGateway\ChannelGatewayRegistry;
use App\Service\ChannelGateway\Dto\Response\ChannelResponse;
use App\Service\ChannelGateway\Dto\Response\ShipOrderResponse;
use App\Service\Fulfillment\FulfillmentCompletionService;
use App\Service\OrderSync\ChannelStatusMapper;
use App\Service\OrderSync\OrderValidationService;
use App\Service\OrderSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class OrderSyncServiceTest extends TestCase
{
    private OrderRepository&MockObject $orderRepository;
    private OrderSyncStateRepository&MockObject $syncStateRepository;
    private ChannelProductRepository&MockObject $channelProductRepository;
    private ChannelGatewayRegistry&MockObject $gatewayRegistry;
    private ChannelStatusMapper&MockObject $statusMapper;
    private OrderValidationService&MockObject $validationService;
    private FulfillmentCompletionService&MockObject $fulfillmentCompletionService;
    private BusinessNoGenerator&MockObject $businessNoGenerator;
    private EntityManagerInterface&MockObject $entityManager;
    private MessageBusInterface&MockObject $messageBus;
    private LoggerInterface&MockObject $logger;
    private OrderSyncService $service;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->syncStateRepository = $this->createMock(OrderSyncStateRepository::class);
        $this->channelProductRepository = $this->createMock(ChannelProductRepository::class);
        $this->gatewayRegistry = $this->createMock(ChannelGatewayRegistry::class);
        $this->statusMapper = $this->createMock(ChannelStatusMapper::class);
        $this->validationService = $this->createMock(OrderValidationService::class);
        $this->fulfillmentCompletionService = $this->createMock(FulfillmentCompletionService::class);
        $this->businessNoGenerator = $this->createMock(BusinessNoGenerator::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Default mock for business number generation
        $this->businessNoGenerator->method('generatePlatformOrderNo')
            ->willReturn('PO'.date('Ymd').'00001');

        $this->service = new OrderSyncService(
            $this->orderRepository,
            $this->syncStateRepository,
            $this->channelProductRepository,
            $this->gatewayRegistry,
            $this->statusMapper,
            $this->validationService,
            $this->fulfillmentCompletionService,
            $this->businessNoGenerator,
            $this->entityManager,
            $this->messageBus,
            $this->logger,
        );
    }

    public function testConfirmOrder(): void
    {
        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getCode')->willReturn('kickscrew');

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');
        $order->method('getSalesChannel')->willReturn($channel);
        $order->method('getExternalOrderId')->willReturn('EXT123');

        $syncState = $this->createMock(OrderSyncState::class);
        $this->syncStateRepository->method('getOrCreate')->willReturn($syncState);

        $gateway = $this->createMock(ChannelGatewayInterface::class);
        $gateway->method('supports')->willReturn(true);

        $response = new ChannelResponse(true, 'kickscrew', 'EXT123');
        $gateway->expects($this->once())
            ->method('confirmOrder')
            ->willReturn($response);

        $this->gatewayRegistry->method('has')->willReturn(true);
        $this->gatewayRegistry->expects($this->once())
            ->method('get')
            ->with('kickscrew')
            ->willReturn($gateway);

        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist');

        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush');

        $result = $this->service->confirmOrder($order);

        $this->assertInstanceOf(OrderSyncLog::class, $result);
    }

    public function testShipOrder(): void
    {
        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getCode')->willReturn('kickscrew');

        $fulfillment = $this->createMock(\App\Entity\Fulfillment::class);
        $fulfillment->method('getTrackingNumber')->willReturn('SF123456789');
        $fulfillment->method('getShippingCarrier')->willReturn('SF');
        $fulfillment->method('getShippedAt')->willReturn(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        $fulfillments = new \Doctrine\Common\Collections\ArrayCollection([$fulfillment]);

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('order-123');
        $order->method('getSalesChannel')->willReturn($channel);
        $order->method('getExternalOrderId')->willReturn('EXT123');
        $order->method('getFulfillments')->willReturn($fulfillments);

        $syncState = $this->createMock(OrderSyncState::class);
        $this->syncStateRepository->method('getOrCreate')->willReturn($syncState);

        $gateway = $this->createMock(ChannelGatewayInterface::class);
        $gateway->method('supports')->willReturn(true);

        $response = new ShipOrderResponse(true, 'kickscrew', 'EXT123', true);
        $gateway->expects($this->once())
            ->method('shipOrder')
            ->willReturn($response);

        $this->gatewayRegistry->method('has')->willReturn(true);
        $this->gatewayRegistry->expects($this->once())
            ->method('get')
            ->willReturn($gateway);

        $result = $this->service->shipOrder($order);

        $this->assertInstanceOf(OrderSyncLog::class, $result);
    }
}
