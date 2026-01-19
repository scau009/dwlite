-- Entity: App\Entity\Fulfillment
-- Description: Fulfillment orders (created after allocation, one order may have multiple fulfillments)

CREATE TABLE `fulfillments` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `fulfillment_no` VARCHAR(30) NOT NULL UNIQUE,
    `order_id` VARCHAR(26) NOT NULL,
    `fulfillment_type` VARCHAR(30) NOT NULL COMMENT 'platform_warehouse, merchant_warehouse',
    `merchant_id` VARCHAR(26) NULL COMMENT 'For merchant warehouse fulfillment',
    `warehouse_id` VARCHAR(26) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, processing, shipped, delivered, cancelled, rejected, expired',
    `allocation_source` VARCHAR(20) NULL COMMENT 'auto, manual',
    `allocation_attempt` INT NOT NULL DEFAULT 1 COMMENT 'Allocation attempt number',
    `shipping_carrier` VARCHAR(50) NULL,
    `tracking_number` VARCHAR(100) NULL,
    `tracking_url` VARCHAR(500) NULL,
    `notified_at` DATETIME NULL COMMENT 'Merchant notification time',
    `shipped_at` DATETIME NULL,
    `delivered_at` DATETIME NULL,
    `cancelled_at` DATETIME NULL,
    `completed_at` DATETIME NULL COMMENT 'Completion timestamp',
    `rejected_at` DATETIME NULL COMMENT 'Rejection timestamp (merchant reject or timeout)',
    `rejection_reason` TEXT NULL COMMENT 'Rejection reason',
    `deadline_at` DATETIME NULL COMMENT 'Self-fulfillment response deadline',
    `excluded_merchant_ids` JSON NULL COMMENT 'Excluded merchant IDs for reallocation',
    `cancel_reason` TEXT NULL,
    `remark` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_fulfillment_order` (`order_id`),
    INDEX `idx_fulfillment_type` (`fulfillment_type`),
    INDEX `idx_fulfillment_status` (`status`),
    INDEX `idx_fulfillment_merchant` (`merchant_id`),
    INDEX `idx_fulfillment_warehouse` (`warehouse_id`),
    INDEX `idx_fulfillment_allocation` (`allocation_source`, `allocation_attempt`),
    INDEX `idx_fulfillment_deadline` (`deadline_at`),
    CONSTRAINT `fk_fulfillment_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
    CONSTRAINT `fk_fulfillment_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fulfillment_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fulfillments';

ALTER TABLE fulfillments ADD COLUMN completed_at DATETIME NULL COMMENT 'Completion timestamp' AFTER cancelled_at;
ALTER TABLE fulfillments ADD COLUMN allocation_source VARCHAR(20) NULL COMMENT 'auto, manual' after status;
ALTER TABLE fulfillments ADD COLUMN    `allocation_attempt` INT NOT NULL DEFAULT 1 COMMENT 'Allocation attempt number' after allocation_source;

-- 添加拒绝相关字段
ALTER TABLE fulfillments ADD COLUMN `rejected_at` DATETIME NULL COMMENT 'Rejection timestamp (merchant reject or timeout)' AFTER `completed_at`;
ALTER TABLE fulfillments ADD COLUMN `rejection_reason` TEXT NULL COMMENT 'Rejection reason' AFTER `rejected_at`;

-- 添加自履约截止时间字段
ALTER TABLE fulfillments ADD COLUMN `deadline_at` DATETIME NULL COMMENT 'Self-fulfillment response deadline' AFTER `rejection_reason`;

-- 添加重新分配时排除的商户ID列表
ALTER TABLE fulfillments ADD COLUMN `excluded_merchant_ids` JSON NULL COMMENT 'Excluded merchant IDs for reallocation' AFTER `deadline_at`;

-- 添加缺失的索引
ALTER TABLE fulfillments ADD INDEX `idx_fulfillment_allocation` (`allocation_source`, `allocation_attempt`);
ALTER TABLE fulfillments ADD INDEX `idx_fulfillment_deadline` (`deadline_at`);

-- 更新status字段的注释（添加rejected, expired状态说明）
ALTER TABLE fulfillments MODIFY COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, processing, shipped, delivered, cancelled, rejected, expired';
