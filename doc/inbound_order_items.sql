-- Entity: App\Entity\InboundOrderItem
-- Description: Inbound order line items

CREATE TABLE `inbound_order_items` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `inbound_order_id` VARCHAR(26) NOT NULL,
    `product_sku_id` VARCHAR(26) NULL,
    `sku_code` VARCHAR(50) NULL COMMENT 'SKU snapshot',
    `color_code` VARCHAR(20) NULL,
    `size_value` VARCHAR(20) NULL,
    `spec_info` JSON NULL,
    `product_name` VARCHAR(255) NULL,
    `product_image` VARCHAR(500) NULL,
    `expectedQuantity` INT NOT NULL COMMENT 'Expected quantity',
    `receivedQuantity` INT NOT NULL DEFAULT 0,
    `damagedQuantity` INT NOT NULL DEFAULT 0,
    `unitCost` DECIMAL(10,2) NULL COMMENT 'Unit cost',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, received, partial, over, missing',
    `warehouseRemark` TEXT NULL,
    `receivedAt` DATETIME NULL,
    `createdAt` DATETIME NOT NULL,
    `updatedAt` DATETIME NOT NULL,
    INDEX `idx_inbound_item_order` (`inbound_order_id`),
    INDEX `idx_inbound_item_sku` (`product_sku_id`),
    UNIQUE INDEX `uniq_inbound_order_sku` (`inbound_order_id`, `product_sku_id`),
    CONSTRAINT `fk_inbound_item_order` FOREIGN KEY (`inbound_order_id`) REFERENCES `inbound_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_inbound_item_sku` FOREIGN KEY (`product_sku_id`) REFERENCES `product_skus` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inbound order items';