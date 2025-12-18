-- Entity: App\Entity\Merchant
-- Description: Merchant/seller accounts

CREATE TABLE `merchants` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `user_id` VARCHAR(26) NOT NULL COMMENT 'User ID',
    `name` VARCHAR(100) NOT NULL COMMENT 'Merchant name',
    `logo` VARCHAR(255) NULL COMMENT 'Merchant logo URL',
    `description` TEXT NULL COMMENT 'Merchant description',
    `contact_name` VARCHAR(50) NOT NULL,
    `contact_phone` VARCHAR(20) NOT NULL,
    `province` VARCHAR(50) NULL,
    `city` VARCHAR(50) NULL,
    `district` VARCHAR(50) NULL,
    `address` VARCHAR(255) NULL,
    `business_license` VARCHAR(100) NULL COMMENT 'Business license number',
    `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, suspended, inactive',
    `approved_at` DATETIME NULL COMMENT 'Approval timestamp',
    `rejected_reason` varchar(255) NULL COMMENT 'Rejection reason',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_merchant_status` (`status`),
    CONSTRAINT `fk_merchant_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Merchants';

alter table merchants add column `logo` VARCHAR(255) NULL COMMENT 'Merchant logo URL' after  name
