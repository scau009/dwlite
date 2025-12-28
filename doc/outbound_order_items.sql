-- Entity: App\Entity\OutboundOrderItem
-- Description: Outbound order line items

CREATE TABLE `outbound_order_items` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `outbound_order_id` VARCHAR(26) NOT NULL,
    `merchant_id` VARCHAR(26) NULL COMMENT 'Merchant snapshot',
    `warehouse_id` VARCHAR(26) NULL COMMENT 'Warehouse snapshot',
    `product_sku_id` VARCHAR(26) NULL COMMENT 'Product SKU reference',
    `sku_code` VARCHAR(50) NULL COMMENT 'SKU snapshot',
    `color_code` VARCHAR(20) NULL,
    `size_value` VARCHAR(20) NULL,
    `spec_info` JSON NULL,
    `product_name` VARCHAR(255) NULL,
    `product_image` VARCHAR(500) NULL,
    `stock_type` VARCHAR(20) NOT NULL DEFAULT 'normal' COMMENT 'Stock type: normal or damaged',
    `quantity` INT NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_outbound_item_outbound` (`outbound_order_id`),
    INDEX `idx_outbound_item_merchant` (`merchant_id`),
    INDEX `idx_outbound_item_warehouse` (`warehouse_id`),
    INDEX `idx_outbound_item_sku` (`product_sku_id`),
    CONSTRAINT `fk_outbound_item_outbound` FOREIGN KEY (`outbound_order_id`) REFERENCES `outbound_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_outbound_item_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_outbound_item_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_outbound_item_sku` FOREIGN KEY (`product_sku_id`) REFERENCES `product_skus` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Outbound order items';

-- Migration for existing tables:
# ALTER TABLE `outbound_order_items` ADD COLUMN `product_sku_id` VARCHAR(26) NULL COMMENT 'Product SKU reference' AFTER `warehouse_id`;
# ALTER TABLE `outbound_order_items` ADD INDEX `idx_outbound_item_sku` (`product_sku_id`);
# ALTER TABLE `outbound_order_items` ADD CONSTRAINT `fk_outbound_item_sku` FOREIGN KEY (`product_sku_id`) REFERENCES `product_skus` (`id`) ON DELETE SET NULL;
# ALTER TABLE `outbound_order_items` ADD COLUMN `stock_type` VARCHAR(20) NOT NULL DEFAULT 'normal' COMMENT 'Stock type: normal or damaged' AFTER `product_image`;