-- Entity: App\Entity\Merchant
-- Description: Merchant/seller accounts

CREATE TABLE `merchants` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `name` VARCHAR(100) NOT NULL COMMENT 'Merchant name',
    `code` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Unique merchant code',
    `contact_name` VARCHAR(50) NOT NULL,
    `contact_phone` VARCHAR(20) NOT NULL,
    `contact_email` VARCHAR(100) NULL,
    `address` VARCHAR(255) NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, suspended, inactive',
    `commission_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Platform commission rate (%)',
    `settlement_cycle` VARCHAR(20) NOT NULL DEFAULT 'monthly' COMMENT 'weekly, monthly, quarterly',
    `business_license` VARCHAR(100) NULL COMMENT 'Business license number',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_merchant_code` (`code`),
    INDEX `idx_merchant_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Merchants';