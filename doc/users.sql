-- Entity: App\Entity\User
-- Description: User accounts with email/password authentication
-- Account types: admin (平台管理员), merchant (商户), warehouse (仓库操作员)

CREATE TABLE `users` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `email` VARCHAR(180) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `roles` JSON NOT NULL COMMENT 'User roles array',
    `account_type` VARCHAR(50) NOT NULL DEFAULT 'merchant' COMMENT 'User account type: admin, merchant, warehouse',
    `warehouse_id` VARCHAR(26) NULL COMMENT 'Associated warehouse for warehouse account type',
    `is_verified` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Email verification status',
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_user_email` (`email`),
    INDEX `idx_user_active` (`is_active`),
    INDEX `idx_user_warehouse` (`warehouse_id`),
    CONSTRAINT `fk_user_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User accounts';

-- Migration script for existing databases:
-- ALTER TABLE `users`
--     ADD COLUMN `warehouse_id` VARCHAR(26) NULL COMMENT 'Associated warehouse for warehouse account type' AFTER `account_type`,
--     ADD INDEX `idx_user_warehouse` (`warehouse_id`),
--     ADD CONSTRAINT `fk_user_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE SET NULL;