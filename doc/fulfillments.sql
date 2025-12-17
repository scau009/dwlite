-- Entity: App\Entity\Fulfillment
-- Description: Fulfillment orders (created after allocation, one order may have multiple fulfillments)

CREATE TABLE `fulfillments` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `fulfillment_no` VARCHAR(30) NOT NULL UNIQUE,
    `order_id` VARCHAR(26) NOT NULL,
    `fulfillment_type` VARCHAR(30) NOT NULL COMMENT 'platform_warehouse, merchant_warehouse',
    `merchant_id` VARCHAR(26) NULL COMMENT 'For merchant warehouse fulfillment',
    `warehouse_id` VARCHAR(26) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, processing, shipped, delivered, cancelled',
    `shipping_carrier` VARCHAR(50) NULL,
    `tracking_number` VARCHAR(100) NULL,
    `tracking_url` VARCHAR(500) NULL,
    `notified_at` DATETIME NULL COMMENT 'Merchant notification time',
    `shipped_at` DATETIME NULL,
    `delivered_at` DATETIME NULL,
    `cancelled_at` DATETIME NULL,
    `cancel_reason` TEXT NULL,
    `remark` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_fulfillment_order` (`order_id`),
    INDEX `idx_fulfillment_type` (`fulfillment_type`),
    INDEX `idx_fulfillment_status` (`status`),
    INDEX `idx_fulfillment_merchant` (`merchant_id`),
    INDEX `idx_fulfillment_warehouse` (`warehouse_id`),
    CONSTRAINT `fk_fulfillment_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
    CONSTRAINT `fk_fulfillment_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fulfillment_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fulfillments';