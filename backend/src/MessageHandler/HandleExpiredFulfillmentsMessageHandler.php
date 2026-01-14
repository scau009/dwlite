<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\HandleExpiredFulfillmentsMessage;
use App\Service\Fulfillment\FulfillmentService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class HandleExpiredFulfillmentsMessageHandler
{
    public function __construct(
        private readonly FulfillmentService $fulfillmentService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(HandleExpiredFulfillmentsMessage $message): void
    {
        $this->logger->info('Starting expired fulfillments scan', [
            'scheduledAt' => $message->scheduledAt->format('c'),
        ]);

        try {
            $count = $this->fulfillmentService->handleExpiredFulfillments();

            $this->logger->info('Expired fulfillments scan completed', [
                'expiredCount' => $count,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Expired fulfillments scan error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
