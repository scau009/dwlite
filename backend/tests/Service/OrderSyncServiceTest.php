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
use App\Service\ChannelGateway\Dto\Response\PulledOrderDto;
use App\Service\ChannelGateway\Dto\Response\ReceiverDto;
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

    public function testProcessChannelOrderRethrowsOriginalExceptionWithoutFlushingWhenEntityManagerClosed(): void
    {
        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getId')->willReturn('channel-123');

        $pulledOrder = $this->createPulledOrderDto();
        $originalException = new \RuntimeException('Original failure');

        $this->entityManager->expects($this->once())
            ->method('persist');
        $this->entityManager->expects($this->once())
            ->method('isOpen')
            ->willReturn(false);
        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->orderRepository->expects($this->once())
            ->method('findByExternalOrderId')
            ->with($pulledOrder->externalOrderId, $channel)
            ->willThrowException($originalException);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Skipping sync log flush because EntityManager is closed',
                [
                    'externalOrderId' => $pulledOrder->externalOrderId,
                    'error' => $originalException->getMessage(),
                ]
            );

        $this->expectExceptionObject($originalException);

        $this->service->processChannelOrder($channel, $pulledOrder);
    }

    public function testProcessChannelOrderFlushesFailedSyncLogWhenEntityManagerIsOpen(): void
    {
        $channel = $this->createMock(SalesChannel::class);
        $channel->method('getId')->willReturn('channel-123');

        $pulledOrder = $this->createPulledOrderDto();
        $originalException = new \RuntimeException('Original failure');

        $this->entityManager->expects($this->once())
            ->method('persist');
        $this->entityManager->expects($this->once())
            ->method('isOpen')
            ->willReturn(true);
        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->orderRepository->expects($this->once())
            ->method('findByExternalOrderId')
            ->with($pulledOrder->externalOrderId, $channel)
            ->willThrowException($originalException);

        $this->logger->expects($this->never())
            ->method('warning');

        $this->expectExceptionObject($originalException);

        $this->service->processChannelOrder($channel, $pulledOrder);
    }

    private function createPulledOrderDto(): PulledOrderDto
    {
        return new PulledOrderDto(
            externalOrderId: 'EXT123',
            externalOrderNo: 'EXT123',
            status: 'pending',
            paymentStatus: 'pending',
            receiver: new ReceiverDto(
                name: 'Receiver',
                phone: '13800000000',
                address: 'Test Address',
                province: 'Shanghai',
                city: 'Shanghai',
                district: 'Pudong',
                postalCode: '200000',
            ),
            totalAmount: '100.00',
            productAmount: '100.00',
            shippingAmount: '0.00',
            discountAmount: '0.00',
            currency: 'CNY',
            placedAt: new \DateTimeImmutable('2026-03-12T00:00:00+00:00'),
            paidAt: null,
            items: [],
            buyerRemark: null,
            rawData: null,
        );
    }
}
