-- Entity: App\Entity\FulfillmentItem
-- Description: Fulfillment order items

CREATE TABLE `fulfillment_items` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `fulfillment_id` VARCHAR(26) NOT NULL,
    `order_item_id` VARCHAR(26) NOT NULL,
    `merchant_id` VARCHAR(26) NULL COMMENT 'Snapshot merchant',
    `warehouse_id` VARCHAR(26) NULL COMMENT 'Snapshot warehouse',
    `channel_product_source_id` VARCHAR(26) NULL COMMENT 'Source tracking for allocation',
    `inventory_listing_id` VARCHAR(26) NULL COMMENT 'Listing tracking',
    `merchant_inventory_id` VARCHAR(26) NULL COMMENT 'Inventory tracking',
    `quantity` INT NOT NULL,
    `list_price` DECIMAL(10, 2) NULL COMMENT 'Listing price snapshot',
    `settlement_price` DECIMAL(10, 2) NULL COMMENT 'Settlement price (merchant earns)',
    `commission_rate` DECIMAL(5, 2) NULL COMMENT 'Commission rate %',
    `commission_amount` DECIMAL(10, 2) NULL COMMENT 'Commission amount',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_fulfillment_item_fulfillment` (`fulfillment_id`),
    INDEX `idx_fulfillment_item_order_item` (`order_item_id`),
    INDEX `idx_fulfillment_item_merchant` (`merchant_id`),
    INDEX `idx_fulfillment_item_warehouse` (`warehouse_id`),
    INDEX `idx_fulfillment_item_source` (`channel_product_source_id`),
    INDEX `idx_fulfillment_item_listing` (`inventory_listing_id`),
    INDEX `idx_fulfillment_item_inventory` (`merchant_inventory_id`),
    CONSTRAINT `fk_fulfillment_item_fulfillment` FOREIGN KEY (`fulfillment_id`) REFERENCES `fulfillments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fulfillment_item_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`),
    CONSTRAINT `fk_fulfillment_item_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fulfillment_item_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fulfillment_item_source` FOREIGN KEY (`channel_product_source_id`) REFERENCES `channel_product_sources` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fulfillment_item_listing` FOREIGN KEY (`inventory_listing_id`) REFERENCES `inventory_listings` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fulfillment_item_inventory` FOREIGN KEY (`merchant_inventory_id`) REFERENCES `merchant_inventories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fulfillment Items';
