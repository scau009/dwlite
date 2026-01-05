<?php

namespace App\Message;

/**
 * Message to sync a single page of products.
 *
 * This message is dispatched for each page of products to be synced.
 * Processing is done asynchronously to handle large datasets.
 */
class SyncProductPageMessage implements AsyncMessageInterface
{
    public function __construct(
        public readonly string $syncJobId,
        public readonly string $provider,
        public readonly int $pageNumber,
        public readonly int $pageSize = 100,
    ) {
    }
}
