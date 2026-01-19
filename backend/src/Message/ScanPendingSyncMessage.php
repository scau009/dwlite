<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message to scan for stale pending sync products.
 *
 * This message is dispatched by the scheduler to find channel products
 * that are stuck in 'pending' sync status and re-trigger their sync.
 * This serves as a compensation mechanism for lost messages.
 */
readonly class ScanPendingSyncMessage
{
    public function __construct(
        public ?string $salesChannelId = null,
        public int $thresholdMinutes = 5,
        public int $limit = 100,
    ) {
    }

    /**
     * Create with default settings.
     */
    public static function create(?string $salesChannelId = null): self
    {
        return new self(
            salesChannelId: $salesChannelId,
        );
    }

    /**
     * Get the threshold datetime for stale products (calculated at execution time).
     */
    public function getThreshold(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify(sprintf('-%d minutes', $this->thresholdMinutes));
    }
}
