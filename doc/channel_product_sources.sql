-- Entity: App\Entity\ChannelProductSource
-- Description: Channel product sources - links platform products to merchant listings

CREATE TABLE `channel_product_sources` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `channel_product_id` VARCHAR(26) NOT NULL,
    `inventory_listing_id` VARCHAR(26) NOT NULL,
    `priority` INT NOT NULL DEFAULT 0 COMMENT 'Lower value = higher priority',
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `sold_quantity` INT NOT NULL DEFAULT 0 COMMENT 'Sold quantity from this source',
    `remark` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE INDEX `uniq_product_listing` (`channel_product_id`, `inventory_listing_id`),
    INDEX `idx_cps_product` (`channel_product_id`),
    INDEX `idx_cps_listing` (`inventory_listing_id`),
    CONSTRAINT `fk_cps_product` FOREIGN KEY (`channel_product_id`) REFERENCES `channel_products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cps_listing` FOREIGN KEY (`inventory_listing_id`) REFERENCES `inventory_listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Channel product sources';
