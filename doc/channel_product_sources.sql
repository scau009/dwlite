-- Entity: App\Entity\ChannelProductSource
-- Description: Sourcing configuration for channel products (where to fulfill from)

CREATE TABLE `channel_product_sources` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `channel_product_id` VARCHAR(26) NOT NULL,
    `source_type` VARCHAR(30) NOT NULL COMMENT 'merchant_inventory, platform_warehouse',
    `merchant_inventory_id` VARCHAR(26) NULL COMMENT 'Link to merchant inventory',
    `warehouse_id` VARCHAR(26) NULL COMMENT 'Platform warehouse',
    `priority` INT NOT NULL DEFAULT 0 COMMENT 'Higher = checked first',
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_cps_channel_product` (`channel_product_id`),
    INDEX `idx_cps_merchant_inventory` (`merchant_inventory_id`),
    INDEX `idx_cps_warehouse` (`warehouse_id`),
    INDEX `idx_cps_priority` (`priority`),
    INDEX `idx_cps_active` (`is_active`),
    CONSTRAINT `fk_cps_channel_product` FOREIGN KEY (`channel_product_id`) REFERENCES `channel_products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cps_merchant_inventory` FOREIGN KEY (`merchant_inventory_id`) REFERENCES `merchant_inventories` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cps_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Channel product sourcing';