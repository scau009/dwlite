-- Entity: App\Entity\MerchantSalesChannel
-- Description: Merchant-SalesChannel connections with credentials

CREATE TABLE `merchant_sales_channels` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_id` VARCHAR(26) NOT NULL,
    `sales_channel_id` VARCHAR(26) NOT NULL,
    `shop_id` VARCHAR(100) NOT NULL COMMENT 'External shop/store ID',
    `shop_name` VARCHAR(100) NOT NULL,
    `app_key` VARCHAR(100) NULL COMMENT 'API app key',
    `app_secret` VARCHAR(255) NULL COMMENT 'API app secret (encrypted)',
    `access_token` VARCHAR(500) NULL COMMENT 'OAuth access token (encrypted)',
    `refresh_token` VARCHAR(500) NULL COMMENT 'OAuth refresh token (encrypted)',
    `token_expires_at` DATETIME NULL,
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `last_synced_at` DATETIME NULL COMMENT 'Last successful sync time',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_msc_merchant` (`merchant_id`),
    INDEX `idx_msc_channel` (`sales_channel_id`),
    INDEX `idx_msc_shop` (`shop_id`),
    INDEX `idx_msc_active` (`is_active`),
    UNIQUE INDEX `uniq_merchant_channel_shop` (`merchant_id`, `sales_channel_id`, `shop_id`),
    CONSTRAINT `fk_msc_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_msc_channel` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Merchant-SalesChannel connections';