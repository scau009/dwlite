<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Trigger sources for channel product synchronization.
 */
enum SyncTriggerSource: string
{
    // Listing operations
    case LISTING_CREATE = 'listing_create';
    case LISTING_UPDATE = 'listing_update';
    case LISTING_ACTIVATE = 'listing_activate';
    case LISTING_PAUSE = 'listing_pause';
    case LISTING_DELETE = 'listing_delete';

    // Inventory operations
    case INVENTORY_INBOUND = 'inventory_inbound';
    case INVENTORY_OUTBOUND = 'inventory_outbound';
    case INVENTORY_ADJUST = 'inventory_adjust';
    case INVENTORY_IMPORT = 'inventory_import';

    // Order operations
    case ORDER_CANCEL = 'order_cancel';

    // System operations
    case MANUAL = 'manual';
    case SCHEDULED = 'scheduled';
    case COMPENSATION = 'compensation';

    /**
     * Check if this trigger is from inventory change.
     */
    public function isInventoryChange(): bool
    {
        return in_array($this, [
            self::INVENTORY_INBOUND,
            self::INVENTORY_OUTBOUND,
            self::INVENTORY_ADJUST,
            self::INVENTORY_IMPORT,
            self::ORDER_CANCEL,
        ], true);
    }

    /**
     * Check if this trigger is from listing operation.
     */
    public function isListingOperation(): bool
    {
        return in_array($this, [
            self::LISTING_CREATE,
            self::LISTING_UPDATE,
            self::LISTING_ACTIVATE,
            self::LISTING_PAUSE,
            self::LISTING_DELETE,
        ], true);
    }

    /**
     * Check if this trigger is from system.
     */
    public function isSystemTrigger(): bool
    {
        return in_array($this, [
            self::MANUAL,
            self::SCHEDULED,
            self::COMPENSATION,
        ], true);
    }

    /**
     * Get human-readable label.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::LISTING_CREATE => 'Listing Created',
            self::LISTING_UPDATE => 'Listing Updated',
            self::LISTING_ACTIVATE => 'Listing Activated',
            self::LISTING_PAUSE => 'Listing Paused',
            self::LISTING_DELETE => 'Listing Deleted',
            self::INVENTORY_INBOUND => 'Inventory Inbound',
            self::INVENTORY_OUTBOUND => 'Inventory Outbound',
            self::INVENTORY_ADJUST => 'Inventory Adjustment',
            self::INVENTORY_IMPORT => 'Inventory Import',
            self::ORDER_CANCEL => 'Order Cancelled',
            self::MANUAL => 'Manual Trigger',
            self::SCHEDULED => 'Scheduled Sync',
            self::COMPENSATION => 'Compensation Scan',
        };
    }
}
