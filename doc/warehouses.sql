-- Entity: App\Entity\Warehouse
-- Description: Warehouses for inventory management

CREATE TABLE `warehouses` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `code` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Unique warehouse code',
    `name` VARCHAR(100) NOT NULL,
    `type` VARCHAR(20) NOT NULL COMMENT 'platform, merchant',
    `merchant_id` VARCHAR(26) NULL COMMENT 'NULL for platform warehouses',
    `province` VARCHAR(50) NULL,
    `city` VARCHAR(50) NULL,
    `district` VARCHAR(50) NULL,
    `address` VARCHAR(255) NOT NULL,
    `postal_code` VARCHAR(20) NULL,
    `contact_name` VARCHAR(50) NOT NULL,
    `contact_phone` VARCHAR(20) NOT NULL,
    `contact_email` VARCHAR(100) NULL,
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `priority` INT NOT NULL DEFAULT 0 COMMENT 'Allocation priority (higher = first)',
    `capacity` INT NULL COMMENT 'Storage capacity',
    `wms_id` VARCHAR(100) NULL COMMENT 'External WMS system ID',
    `wms_api_endpoint` VARCHAR(255) NULL,
    `wms_config` JSON NULL COMMENT 'WMS integration config',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_warehouse_code` (`code`),
    INDEX `idx_warehouse_type` (`type`),
    INDEX `idx_warehouse_merchant` (`merchant_id`),
    INDEX `idx_warehouse_active` (`is_active`),
    INDEX `idx_warehouse_priority` (`priority`),
    CONSTRAINT `fk_warehouse_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Warehouses';