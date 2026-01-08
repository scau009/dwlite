-- Entity: App\Entity\FulfillmentAllocationLog
-- Description: Fulfillment allocation audit logs

CREATE TABLE `fulfillment_allocation_logs` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `order_id` VARCHAR(26) NOT NULL,
    `order_item_id` VARCHAR(26) NULL,
    `attempt_number` INT NOT NULL,
    `selected_merchant_id` VARCHAR(26) NULL,
    `selected_source_id` VARCHAR(26) NULL,
    `result` VARCHAR(20) NOT NULL COMMENT 'success, no_stock, price_invalid, rejected, expired, no_source',
    `candidate_sources` JSON NULL COMMENT 'All evaluated sources with scores',
    `failure_reason` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_fal_order` (`order_id`),
    INDEX `idx_fal_order_item` (`order_item_id`),
    INDEX `idx_fal_merchant` (`selected_merchant_id`),
    INDEX `idx_fal_result` (`result`),
    INDEX `idx_fal_created` (`created_at`),
    CONSTRAINT `fk_fal_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fal_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fal_merchant` FOREIGN KEY (`selected_merchant_id`) REFERENCES `merchants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fulfillment allocation audit logs';
