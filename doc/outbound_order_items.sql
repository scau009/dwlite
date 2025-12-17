-- Entity: App\Entity\OutboundOrderItem
-- Description: Outbound order line items

CREATE TABLE `outbound_order_items` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `outbound_order_id` VARCHAR(26) NOT NULL,
    `merchant_id` VARCHAR(26) NULL COMMENT 'Merchant snapshot',
    `warehouse_id` VARCHAR(26) NULL COMMENT 'Warehouse snapshot',
    `sku_code` VARCHAR(50) NULL COMMENT 'SKU snapshot',
    `color_code` VARCHAR(20) NULL,
    `size_value` VARCHAR(20) NULL,
    `spec_info` JSON NULL,
    `product_name` VARCHAR(255) NULL,
    `product_image` VARCHAR(500) NULL,
    `quantity` INT NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_outbound_item_outbound` (`outbound_order_id`),
    INDEX `idx_outbound_item_merchant` (`merchant_id`),
    INDEX `idx_outbound_item_warehouse` (`warehouse_id`),
    CONSTRAINT `fk_outbound_item_outbound` FOREIGN KEY (`outbound_order_id`) REFERENCES `outbound_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_outbound_item_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_outbound_item_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Outbound order items';