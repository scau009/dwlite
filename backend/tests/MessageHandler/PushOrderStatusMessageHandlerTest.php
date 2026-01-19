<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Order;
use App\Entity\OrderSyncLog;
use App\Message\AllocateOrderMessage;
use App\Message\PushOrderStatusMessage;
use App\MessageHandler\PushOrderStatusMessageHandler;
use App\Repository\OrderRepository;
use App\Service\ChannelGateway\Exception\ChannelGatewayException;
use App\Service\OrderSync\OrderValidationService;
use App\Service\OrderSyncService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class PushOrderStatusMessageHandlerTest extends TestCase
{
    private OrderRepository&MockObject $orderRepo;
    private OrderSyncService&MockObject $orderSyncService;
    private OrderValidationService&MockObject $validationService;
    private LockFactory&MockObject $lockFactory;
    private MessageBusInterface&MockObject $messageBus;
    private LoggerInterface&MockObject $logger;
    private PushOrderStatusMessageHandler $handler;

    protected function setUp(): void
    {
        $this->orderRepo = $this->createMock(OrderRepository::class);
        $this->orderSyncService = $this->createMock(OrderSyncService::class);
        $this->validationService = $this->createMock(OrderValidationService::class);
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new PushOrderStatusMessageHandler(
            $this->orderRepo,
            $this->orderSyncService,
            $this->validationService,
            $this->lockFactory,
            $this->messageBus,
            $this->logger,
        );
    }

    public function testConfirmSuccessTriggersAllocation(): void
    {
        $orderId = '01HTEST123456789ABCDEFGH';
        $message = PushOrderStatusMessage::confirm($orderId);

        // Setup lock
        $lock = $this->createMock(LockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');
        $this->lockFactory->expects($this->once())->method('createLock')->willReturn($lock);

        // Setup order
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn($orderId);
        $order->method('canAllocate')->willReturn(true);
        $this->orderRepo->expects($this->once())->method('find')->with($orderId)->willReturn($order);

        // Setup validation
        $this->validationService->expects($this->once())->method('canConfirm')->with($order)->willReturn(true);

        // Setup sync log - success
        $syncLog = $this->createMock(OrderSyncLog::class);
        $syncLog->method('isSuccess')->willReturn(true);
        $syncLog->method('getDurationMs')->willReturn(100);
        $this->orderSyncService->expects($this->once())->method('confirmOrder')->with($order)->willReturn($syncLog);

        // Expect allocation message to be dispatched
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($msg) use ($orderId) {
                return $msg instanceof AllocateOrderMessage
                    && $msg->orderId === $orderId
                    && $msg->excludedMerchantIds === []
                    && $msg->attemptNumber === 1;
            }))
            ->willReturn(new Envelope(new AllocateOrderMessage($orderId)));

        ($this->handler)($message);
    }

    public function testConfirmSuccessDoesNotTriggerAllocationWhenOrderCannotAllocate(): void
    {
        $orderId = '01HTEST123456789ABCDEFGH';
        $message = PushOrderStatusMessage::confirm($orderId);

        // Setup lock
        $lock = $this->createMock(LockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');
        $this->lockFactory->expects($this->once())->method('createLock')->willReturn($lock);

        // Setup order - canAllocate returns false
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn($orderId);
        $order->method('canAllocate')->willReturn(false);
        $this->orderRepo->expects($this->once())->method('find')->with($orderId)->willReturn($order);

        // Setup validation
        $this->validationService->expects($this->once())->method('canConfirm')->with($order)->willReturn(true);

        // Setup sync log - success
        $syncLog = $this->createMock(OrderSyncLog::class);
        $syncLog->method('isSuccess')->willReturn(true);
        $syncLog->method('getDurationMs')->willReturn(100);
        $this->orderSyncService->expects($this->once())->method('confirmOrder')->with($order)->willReturn($syncLog);

        // Allocation should NOT be dispatched
        $this->messageBus->expects($this->never())->method('dispatch');

        ($this->handler)($message);
    }

    public function testConfirmFailureDoesNotTriggerAllocation(): void
    {
        $orderId = '01HTEST123456789ABCDEFGH';
        $message = PushOrderStatusMessage::confirm($orderId);

        // Setup lock
        $lock = $this->createMock(LockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');
        $this->lockFactory->expects($this->once())->method('createLock')->willReturn($lock);

        // Setup order
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn($orderId);
        $this->orderRepo->expects($this->once())->method('find')->with($orderId)->willReturn($order);

        // Setup validation
        $this->validationService->expects($this->once())->method('canConfirm')->with($order)->willReturn(true);

        // Setup sync log - failure
        $syncLog = $this->createMock(OrderSyncLog::class);
        $syncLog->method('isSuccess')->willReturn(false);
        $syncLog->method('getErrorCode')->willReturn('ERR001');
        $syncLog->method('getErrorMessage')->willReturn('Confirm failed');
        $this->orderSyncService->expects($this->once())->method('confirmOrder')->with($order)->willReturn($syncLog);

        // Allocation should NOT be dispatched
        $this->messageBus->expects($this->never())->method('dispatch');

        ($this->handler)($message);
    }

    public function testShipOperationDoesNotTriggerAllocation(): void
    {
        $orderId = '01HTEST123456789ABCDEFGH';
        $message = PushOrderStatusMessage::ship($orderId);

        // Setup lock
        $lock = $this->createMock(LockInterface::class);
        $lock->expects($this->once())->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');
        $this->lockFactory->expects($this->once())->method('createLock')->willReturn($lock);

        // Setup order
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn($orderId);
        $this->orderRepo->expects($this->once())->method('find')->with($orderId)->willReturn($order);

        // Setup sync log - success
        $syncLog = $this->createMock(OrderSyncLog::class);
        $syncLog->method('isSuccess')->willReturn(true);
        $syncLog->method('getDurationMs')->willReturn(100);
        $this->orderSyncService->expects($this->once())->method('shipOrder')->with($order)->willReturn($syncLog);

        // Allocation should NOT be dispatched for ship operation
        $this->messageBus->expects($this->never())->method('dispatch');

        ($this->handler)($message);
    }
}
