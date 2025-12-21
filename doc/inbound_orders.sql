-- Entity: App\Entity\InboundOrder
-- Description: Inbound orders (merchant to warehouse delivery orders)

CREATE TABLE `inbound_orders` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `order_no` VARCHAR(32) NOT NULL UNIQUE COMMENT 'Inbound order number (IB...)',
    `merchant_id` VARCHAR(26) NOT NULL,
    `warehouse_id` VARCHAR(26) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'draft, pending, shipped, arrived, receiving, completed, partial_completed, cancelled',

    -- Quantity stats
    `total_sku_count` INT NOT NULL DEFAULT 0 COMMENT 'Number of SKU types',
    `total_quantity` INT NOT NULL DEFAULT 0 COMMENT 'Expected total quantity',
    `received_quantity` INT NOT NULL DEFAULT 0 COMMENT 'Received total quantity',

    -- Time milestones
    `expected_arrival_date` DATE NULL COMMENT 'Expected arrival date',
    `submitted_at` DATETIME NULL COMMENT 'Submitted time (draft -> pending)',
    `shipped_at` DATETIME NULL COMMENT 'Shipped time',
    `arrived_at` DATETIME NULL COMMENT 'Arrived at warehouse time',
    `completed_at` DATETIME NULL COMMENT 'Completed time',
    `cancelled_at` DATETIME NULL COMMENT 'Cancelled time',

    -- Notes
    `merchant_notes` TEXT NULL COMMENT 'Merchant notes',
    `warehouse_notes` TEXT NULL COMMENT 'Warehouse notes',
    `cancel_reason` VARCHAR(100) NULL COMMENT 'Cancel reason',

    -- Timestamps
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,

    -- Indexes
    INDEX `idx_inbound_order_no` (`order_no`),
    INDEX `idx_inbound_merchant` (`merchant_id`),
    INDEX `idx_inbound_warehouse` (`warehouse_id`),
    INDEX `idx_inbound_status` (`status`),
    INDEX `idx_inbound_created` (`created_at`),

    -- Foreign Keys
    CONSTRAINT `fk_inbound_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`),
    CONSTRAINT `fk_inbound_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inbound orders';
