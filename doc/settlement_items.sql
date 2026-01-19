-- Entity: App\Entity\SettlementItem
-- Description: Settlement item details (one item per fulfillment item)

CREATE TABLE `settlement_items` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `settlement_id` VARCHAR(26) NOT NULL,
    `fulfillment_item_id` VARCHAR(26) NOT NULL,
    `sku_code` VARCHAR(100) NULL COMMENT 'SKU code snapshot',
    `product_name` VARCHAR(255) NULL COMMENT 'Product name snapshot',
    `quantity` INT NOT NULL COMMENT 'Quantity',
    `unit_price` DECIMAL(10,2) NOT NULL COMMENT 'Unit settlement price',
    `gross_amount` DECIMAL(12,2) NOT NULL COMMENT 'Subtotal (quantity * unit_price)',
    `commission_rate` DECIMAL(5,2) NOT NULL COMMENT 'Commission rate',
    `commission_amount` DECIMAL(10,2) NOT NULL COMMENT 'Commission amount',
    `net_amount` DECIMAL(12,2) NOT NULL COMMENT 'Net amount (gross - commission)',
    `created_at` DATETIME NOT NULL,
    INDEX `idx_si_settlement` (`settlement_id`),
    INDEX `idx_si_fulfillment_item` (`fulfillment_item_id`),
    CONSTRAINT `fk_si_settlement` FOREIGN KEY (`settlement_id`) REFERENCES `settlements` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_si_fulfillment_item` FOREIGN KEY (`fulfillment_item_id`) REFERENCES `fulfillment_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Settlement items';
