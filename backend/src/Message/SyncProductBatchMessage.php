<?php

namespace App\Message;

/**
 * Message to sync a batch of products using cursor-based pagination.
 *
 * This message is dispatched for each batch of products to be synced.
 * Each handler processes a batch and dispatches the next batch message
 * if there are more products.
 */
class SyncProductBatchMessage implements AsyncMessageInterface
{
    public function __construct(
        public readonly string $syncJobId,
        public readonly string $provider,
        public readonly ?int $afterRank = null,
        public readonly int $batchSize = 100,
        public readonly ?string $productTypeFilter = null,
        public readonly int $batchNumber = 1,
    ) {
    }
}
