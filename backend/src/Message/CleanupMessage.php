<?php

namespace App\Message;

/**
 * Scheduled task message for periodic cleanup operations.
 * This message is dispatched automatically by the scheduler.
 */
class CleanupMessage
{
    public function __construct(
        public readonly \DateTimeImmutable $scheduledAt,
    ) {
    }
}
