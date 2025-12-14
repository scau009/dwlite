<?php

namespace App\MessageHandler;

use App\Message\CleanupMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CleanupMessageHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CleanupMessage $message): void
    {
        $this->logger->info('Running scheduled cleanup task', [
            'scheduled_at' => $message->scheduledAt->format('Y-m-d H:i:s'),
        ]);

        // Example cleanup operations:
        // - Clear expired cache entries
        // - Remove old temporary files
        // - Archive old records
        // - Send summary reports

        $this->logger->info('Scheduled cleanup task completed');
    }
}
