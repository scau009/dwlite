-- Entity: App\Entity\FulfillmentItem
-- Description: Fulfillment line items with settlement pricing snapshot

CREATE TABLE `fulfillment_items` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `fulfillment_id` VARCHAR(26) NOT NULL,
    `order_item_id` VARCHAR(26) NOT NULL,
    `merchant_id` VARCHAR(26) NULL COMMENT 'Merchant snapshot',
    `warehouse_id` VARCHAR(26) NULL COMMENT 'Warehouse snapshot',
    `quantity` INT NOT NULL,
    `list_price` DECIMAL(10,2) NULL COMMENT 'Listed price snapshot',
    `settlement_price` DECIMAL(10,2) NULL COMMENT 'Settlement price snapshot',
    `commission_rate` DECIMAL(5,2) NULL COMMENT 'Commission rate %',
    `commission_amount` DECIMAL(10,2) NULL COMMENT 'Commission amount',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_fulfillment_item_fulfillment` (`fulfillment_id`),
    INDEX `idx_fulfillment_item_order_item` (`order_item_id`),
    INDEX `idx_fulfillment_item_merchant` (`merchant_id`),
    INDEX `idx_fulfillment_item_warehouse` (`warehouse_id`),
    CONSTRAINT `fk_fi_fulfillment` FOREIGN KEY (`fulfillment_id`) REFERENCES `fulfillments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fi_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`),
    CONSTRAINT `fk_fi_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fi_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fulfillment items';