-- Channel Product Sync Logs
-- Records every synchronization operation for observability and debugging

CREATE TABLE `channel_product_sync_logs` (
    `id` VARCHAR(26) NOT NULL COMMENT 'ULID',
    `channel_product_id` VARCHAR(26) NOT NULL,
    `sales_channel_id` VARCHAR(26) NOT NULL,
    `operation` VARCHAR(50) NOT NULL COMMENT 'aggregate | push_product | update_stock_price | delist',
    `trigger_source` VARCHAR(50) NOT NULL COMMENT 'listing_create | listing_update | inventory_inbound | inventory_outbound | inventory_adjust | manual | scheduled',
    `trigger_listing_id` VARCHAR(26) NULL COMMENT 'The InventoryListing that triggered this sync',
    `trigger_merchant_id` VARCHAR(26) NULL COMMENT 'The Merchant that triggered this sync',
    `trigger_inventory_id` VARCHAR(26) NULL COMMENT 'The MerchantInventory that triggered this sync (for inventory changes)',

    -- Snapshot before sync
    `before_data` JSON NULL COMMENT 'State before sync {price, stock, status, externalId}',

    -- State after sync
    `after_data` JSON NULL COMMENT 'State after sync {price, stock, status, externalId}',

    -- Result
    `status` VARCHAR(20) NOT NULL COMMENT 'pending | processing | success | failed | skipped',
    `error_code` VARCHAR(50) NULL,
    `error_message` TEXT NULL,
    `external_response` JSON NULL COMMENT 'Response from external channel API',

    -- Timing
    `started_at` DATETIME NOT NULL,
    `completed_at` DATETIME NULL,
    `duration_ms` INT UNSIGNED NULL COMMENT 'Processing duration in milliseconds',

    `created_at` DATETIME NOT NULL,

    PRIMARY KEY (`id`),
    KEY `idx_channel_product` (`channel_product_id`),
    KEY `idx_sales_channel` (`sales_channel_id`),
    KEY `idx_status` (`status`),
    KEY `idx_trigger_merchant` (`trigger_merchant_id`),
    KEY `idx_trigger_listing` (`trigger_listing_id`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_operation_status` (`operation`, `status`),
    KEY `idx_started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Channel product synchronization logs for observability';
