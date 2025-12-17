-- Entity: App\Entity\ChannelProduct
-- Description: Products published to sales channels

CREATE TABLE `channel_products` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_sales_channel_id` VARCHAR(26) NOT NULL,
    `product_sku_id` VARCHAR(26) NULL COMMENT 'Linked internal SKU',
    `external_product_id` VARCHAR(100) NOT NULL COMMENT 'Channel product ID',
    `external_sku_id` VARCHAR(100) NULL COMMENT 'Channel SKU ID',
    `title` VARCHAR(255) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `stock` INT NOT NULL DEFAULT 0,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, inactive, out_of_stock',
    `image_url` VARCHAR(500) NULL,
    `product_url` VARCHAR(500) NULL,
    `external_data` JSON NULL COMMENT 'Raw channel data',
    `last_synced_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_cp_msc` (`merchant_sales_channel_id`),
    INDEX `idx_cp_sku` (`product_sku_id`),
    INDEX `idx_cp_external` (`external_product_id`),
    INDEX `idx_cp_status` (`status`),
    UNIQUE INDEX `uniq_channel_external` (`merchant_sales_channel_id`, `external_product_id`, `external_sku_id`),
    CONSTRAINT `fk_cp_msc` FOREIGN KEY (`merchant_sales_channel_id`) REFERENCES `merchant_sales_channels` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cp_sku` FOREIGN KEY (`product_sku_id`) REFERENCES `product_skus` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Channel products';