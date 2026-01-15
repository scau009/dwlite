<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Message\HandleExpiredFulfillmentsMessage;
use App\MessageHandler\HandleExpiredFulfillmentsMessageHandler;
use App\Service\Fulfillment\FulfillmentService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class HandleExpiredFulfillmentsMessageHandlerTest extends TestCase
{
    private FulfillmentService&MockObject $fulfillmentService;
    private LoggerInterface&MockObject $logger;
    private HandleExpiredFulfillmentsMessageHandler $handler;

    protected function setUp(): void
    {
        $this->fulfillmentService = $this->createMock(FulfillmentService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new HandleExpiredFulfillmentsMessageHandler(
            $this->fulfillmentService,
            $this->logger,
        );
    }

    public function testHandleSuccessfulScan(): void
    {
        $message = new HandleExpiredFulfillmentsMessage(
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );

        $this->fulfillmentService->expects($this->once())
            ->method('handleExpiredFulfillments')
            ->willReturn(5);

        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                [
                    'Starting expired fulfillments scan',
                    $this->arrayHasKey('scheduledAt'),
                ],
                [
                    'Expired fulfillments scan completed',
                    $this->callback(function ($context) {
                        return isset($context['expiredCount']) && $context['expiredCount'] === 5;
                    }),
                ]
            );

        ($this->handler)($message);
    }

    public function testHandleNoExpiredFulfillments(): void
    {
        $message = new HandleExpiredFulfillmentsMessage(
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );

        $this->fulfillmentService->expects($this->once())
            ->method('handleExpiredFulfillments')
            ->willReturn(0);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Expired fulfillments scan completed',
                $this->callback(function ($context) {
                    return isset($context['expiredCount']) && $context['expiredCount'] === 0;
                })
            );

        ($this->handler)($message);
    }

    public function testHandleException(): void
    {
        $message = new HandleExpiredFulfillmentsMessage(
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );

        $exception = new \RuntimeException('Scan failed');

        $this->fulfillmentService->expects($this->once())
            ->method('handleExpiredFulfillments')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Expired fulfillments scan error',
                $this->callback(function ($context) {
                    return isset($context['error']) && $context['error'] === 'Scan failed';
                })
            );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Scan failed');

        ($this->handler)($message);
    }
}
