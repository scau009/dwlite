<?php

declare(strict_types=1);

namespace App\Tests\Service\Fulfillment;

use App\Entity\Fulfillment;
use App\Entity\Merchant;
use App\Entity\Order;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Repository\FulfillmentRepository;
use App\Service\Fulfillment\FulfillmentService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FulfillmentServiceTest extends TestCase
{
    private FulfillmentRepository&MockObject $fulfillmentRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private MessageBusInterface&MockObject $messageBus;
    private TranslatorInterface&MockObject $translator;
    private LoggerInterface&MockObject $logger;
    private FulfillmentService $service;

    protected function setUp(): void
    {
        $this->fulfillmentRepository = $this->createMock(FulfillmentRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->translator->method('trans')->willReturnCallback(fn(string $key) => $key);

        $this->service = new FulfillmentService(
            $this->fulfillmentRepository,
            $this->entityManager,
            $this->messageBus,
            $this->translator,
            $this->logger,
        );
    }

    public function testGetMerchantFulfillments(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $fulfillment = $this->createMock(Fulfillment::class);

        $this->fulfillmentRepository->expects($this->once())
            ->method('findByMerchantPaginated')
            ->with($merchant, 1, 20, null, null)
            ->willReturn([
                'items' => [$fulfillment],
                'total' => 1,
            ]);

        $result = $this->service->getMerchantFulfillments($merchant, 1, 20);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertCount(1, $result['items']);
    }

    public function testGetMerchantFulfillmentWithAuthorization(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getMerchant')->willReturn($merchant);

        $this->fulfillmentRepository->expects($this->once())
            ->method('find')
            ->with('fulfillment-123')
            ->willReturn($fulfillment);

        $result = $this->service->getMerchantFulfillment('fulfillment-123', $merchant);

        $this->assertSame($fulfillment, $result);
    }

    public function testGetMerchantFulfillmentUnauthorized(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $otherMerchant = $this->createMock(Merchant::class);
        $otherMerchant->method('getId')->willReturn('merchant-456');

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getMerchant')->willReturn($otherMerchant);

        $this->fulfillmentRepository->expects($this->once())
            ->method('find')
            ->with('fulfillment-123')
            ->willReturn($fulfillment);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('fulfillment.not_authorized');

        $this->service->getMerchantFulfillment('fulfillment-123', $merchant);
    }

    public function testAcceptFulfillment(): void
    {
        $merchant = $this->createMock(Merchant::class);

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getId')->willReturn('fulfillment-123');
        $fulfillment->method('getMerchant')->willReturn($merchant);
        $fulfillment->method('isPending')->willReturn(true);
        $fulfillment->method('isMerchantWarehouse')->willReturn(true);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-123');

        $fulfillment->expects($this->once())
            ->method('accept');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->acceptFulfillment($fulfillment, $user);

        $this->assertSame($fulfillment, $result);
    }

    public function testAcceptFulfillmentNotPending(): void
    {
        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('isPending')->willReturn(false);
        $fulfillment->method('isMerchantWarehouse')->willReturn(true);

        $user = $this->createMock(User::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('fulfillment.not_pending');

        $this->service->acceptFulfillment($fulfillment, $user);
    }

    public function testRejectFulfillment(): void
    {
        $merchant = $this->createMock(Merchant::class);
        $merchant->method('getId')->willReturn('merchant-123');

        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getId')->willReturn('fulfillment-123');
        $fulfillment->method('getMerchant')->willReturn($merchant);
        $fulfillment->method('isPending')->willReturn(true);
        $fulfillment->method('isMerchantWarehouse')->willReturn(true);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-123');

        $fulfillment->expects($this->once())
            ->method('reject')
            ->with('No stock available');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->messageBus->expects($this->once())
            ->method('dispatch');

        $result = $this->service->rejectFulfillment($fulfillment, $user, 'No stock available');

        $this->assertSame($fulfillment, $result);
    }

    public function testShipFulfillment(): void
    {
        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getId')->willReturn('fulfillment-123');
        $fulfillment->method('getStatus')->willReturn(Fulfillment::STATUS_PROCESSING);

        $fulfillment->expects($this->once())
            ->method('ship')
            ->with('SF', 'SF123456789');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->shipFulfillment($fulfillment, 'SF', 'SF123456789');

        $this->assertSame($fulfillment, $result);
    }

    public function testConfirmDelivery(): void
    {
        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getId')->willReturn('fulfillment-123');
        $fulfillment->method('getStatus')->willReturn(Fulfillment::STATUS_SHIPPED);

        $fulfillment->expects($this->once())
            ->method('confirmDelivery');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->confirmDelivery($fulfillment);

        $this->assertSame($fulfillment, $result);
    }

    public function testCompleteFulfillment(): void
    {
        $fulfillment = $this->createMock(Fulfillment::class);
        $fulfillment->method('getId')->willReturn('fulfillment-123');
        $fulfillment->method('getStatus')->willReturn(Fulfillment::STATUS_DELIVERED);

        $fulfillment->expects($this->once())
            ->method('complete');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->messageBus->expects($this->once())
            ->method('dispatch');

        $result = $this->service->completeFulfillment($fulfillment);

        $this->assertSame($fulfillment, $result);
    }
}
