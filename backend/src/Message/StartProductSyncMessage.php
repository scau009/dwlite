<?php

namespace App\Message;

/**
 * Message to start a product sync job.
 *
 * This message is dispatched by the scheduler to trigger a sync
 * with a specific provider.
 */
class StartProductSyncMessage
{
    public function __construct(
        public readonly string $provider,
        public readonly \DateTimeImmutable $scheduledAt,
    ) {
    }
}
