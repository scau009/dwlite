<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\SyncTriggerSource;

/**
 * Message to trigger channel product aggregation.
 *
 * When a merchant listing changes (create, update, activate, pause, delete)
 * or inventory changes (inbound, outbound, adjust), this message is dispatched
 * to recalculate the aggregated stock and price for the channel product.
 */
readonly class SyncChannelProductMessage implements AsyncMessageInterface
{
    public function __construct(
        public string $channelProductId,
        public string $triggerSource,
        public ?string $inventoryListingId = null,
        public ?string $merchantId = null,
        public ?string $merchantInventoryId = null,
    ) {
    }

    /**
     * Create from SyncTriggerSource enum.
     */
    public static function create(
        string $channelProductId,
        SyncTriggerSource $triggerSource,
        ?string $inventoryListingId = null,
        ?string $merchantId = null,
        ?string $merchantInventoryId = null,
    ): self {
        return new self(
            $channelProductId,
            $triggerSource->value,
            $inventoryListingId,
            $merchantId,
            $merchantInventoryId,
        );
    }

    /**
     * Get trigger source as enum.
     */
    public function getTriggerSourceEnum(): SyncTriggerSource
    {
        return SyncTriggerSource::from($this->triggerSource);
    }
}
