-- Entity: App\Entity\ChannelProduct
-- Description: Platform channel products - aggregates merchant inventory for external sales channels

CREATE TABLE `channel_products` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `sales_channel_id` VARCHAR(26) NOT NULL,
    `product_sku_id` VARCHAR(26) NOT NULL,
    `platform_price` DECIMAL(10,2) NOT NULL COMMENT 'Platform unified price',
    `platform_compare_at_price` DECIMAL(10,2) NULL COMMENT 'Platform compare-at price',
    `stock_mode` VARCHAR(20) NOT NULL DEFAULT 'aggregate' COMMENT 'aggregate, lowest, fixed',
    `stock_quantity` INT NOT NULL DEFAULT 0 COMMENT 'Calculated external stock',
    `safety_buffer` INT NOT NULL DEFAULT 0 COMMENT 'Safety buffer to prevent overselling',
    `fixed_stock` INT NULL COMMENT 'Fixed stock value (when stock_mode=fixed)',
    `external_id` VARCHAR(100) NULL COMMENT 'External platform product ID',
    `external_url` VARCHAR(500) NULL COMMENT 'External platform product URL',
    `sync_status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, syncing, synced, failed',
    `last_synced_at` DATETIME NULL,
    `sync_error` TEXT NULL COMMENT 'Last sync error message',
    `status` VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'draft, pending, active, paused, rejected',
    `total_sold_quantity` INT NOT NULL DEFAULT 0 COMMENT 'Total sold quantity',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE INDEX `uniq_channel_sku` (`sales_channel_id`, `product_sku_id`),
    INDEX `idx_cp_channel` (`sales_channel_id`),
    INDEX `idx_cp_sku` (`product_sku_id`),
    INDEX `idx_cp_status` (`status`),
    INDEX `idx_cp_external` (`external_id`),
    CONSTRAINT `fk_cp_channel` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channels` (`id`),
    CONSTRAINT `fk_cp_sku` FOREIGN KEY (`product_sku_id`) REFERENCES `product_skus` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Platform channel products';
