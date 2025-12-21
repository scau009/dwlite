-- Entity: App\Entity\Warehouse
-- Description: Warehouses for inventory management

CREATE TABLE `warehouses` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique warehouse code, e.g., WH-SH-001',
    `name` VARCHAR(100) NOT NULL COMMENT 'Warehouse name',
    `short_name` VARCHAR(100) NULL COMMENT 'Short name',

    -- Type and Category
    `type` VARCHAR(20) NOT NULL DEFAULT 'third_party' COMMENT 'Warehouse type: self, third_party, bonded, overseas',
    `category` VARCHAR(20) NOT NULL DEFAULT 'platform' COMMENT 'Ownership: platform, merchant',
    `merchant_id` VARCHAR(26) NULL COMMENT 'Merchant ID for merchant-owned warehouses',
    `description` TEXT NULL COMMENT 'Warehouse description',

    -- Location Info
    `country_code` VARCHAR(2) NOT NULL DEFAULT 'CN' COMMENT 'ISO 3166-1 alpha-2 country code',
    `timezone` VARCHAR(50) NULL COMMENT 'Timezone, e.g., Asia/Shanghai',
    `province` VARCHAR(50) NULL,
    `city` VARCHAR(50) NULL,
    `district` VARCHAR(50) NULL,
    `address` VARCHAR(255) NULL,
    `postal_code` VARCHAR(20) NULL,
    `longitude` DECIMAL(10, 7) NULL COMMENT 'Geographic longitude',
    `latitude` DECIMAL(10, 7) NULL COMMENT 'Geographic latitude',

    -- Contact Info
    `contact_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'Contact person name',
    `contact_phone` VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'Contact phone number',
    `contact_email` VARCHAR(100) NULL COMMENT 'Contact email',

    -- Notes and Status
    `internal_notes` TEXT NULL COMMENT 'Internal notes',
    `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'Status: active, maintenance, disabled',
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT 'Sort order',

    -- Timestamps
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,

    -- Indexes
    INDEX `idx_warehouse_code` (`code`),
    INDEX `idx_warehouse_status` (`status`),
    INDEX `idx_warehouse_type` (`type`),
    INDEX `idx_warehouse_category` (`category`),
    INDEX `idx_warehouse_merchant` (`merchant_id`),
    INDEX `idx_warehouse_country` (`country_code`),

    -- Foreign Keys
    CONSTRAINT `fk_warehouse_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Warehouses for inventory management';
