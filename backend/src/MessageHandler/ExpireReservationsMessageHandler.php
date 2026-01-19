<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ExpireReservationsMessage;
use App\Service\InventoryReservationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * 过期库存预留清理处理器.
 */
#[AsMessageHandler]
class ExpireReservationsMessageHandler
{
    public function __construct(
        private readonly InventoryReservationService $reservationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExpireReservationsMessage $message): void
    {
        $this->logger->info('Starting reservation expiration cleanup', [
            'scheduledAt' => $message->scheduledAt->format(\DateTimeInterface::ATOM),
            'limit' => $message->limit,
        ]);

        try {
            $stats = $this->reservationService->expireReservations($message->limit);

            $this->logger->info('Reservation expiration cleanup completed', [
                'expired' => $stats['expired'],
                'exceptions' => $stats['exceptions'],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Reservation expiration cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
