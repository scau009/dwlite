-- Entity: App\Entity\ApiKey
-- Description: Open API keys for WMS and ERP integrations

CREATE TABLE `api_keys` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `key_id` VARCHAR(32) NOT NULL COMMENT 'Public API Key ID (prefix: dwl_)',
    `key_secret` VARCHAR(64) NOT NULL COMMENT 'Plain API Secret (should be encrypted in production)',
    `name` VARCHAR(100) NOT NULL COMMENT 'Human-readable name',
    `type` VARCHAR(20) NOT NULL COMMENT 'warehouse or merchant',
    `warehouse_id` VARCHAR(26) NULL COMMENT 'FK to warehouses (for warehouse type)',
    `merchant_id` VARCHAR(26) NULL COMMENT 'FK to merchants (for merchant type)',
    `permissions` JSON NOT NULL COMMENT 'Array of allowed permissions',
    `ip_whitelist` JSON NULL COMMENT 'Optional IP whitelist',
    `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, suspended, revoked',
    `last_used_at` DATETIME NULL COMMENT 'Last API call timestamp',
    `expires_at` DATETIME NULL COMMENT 'Optional expiration date',
    `created_by` VARCHAR(26) NULL COMMENT 'User ID who created this key',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE KEY `uk_api_key_key_id` (`key_id`),
    INDEX `idx_api_key_type` (`type`),
    INDEX `idx_api_key_status` (`status`),
    INDEX `idx_api_key_warehouse` (`warehouse_id`),
    INDEX `idx_api_key_merchant` (`merchant_id`),
    CONSTRAINT `fk_api_key_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_api_key_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_api_key_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Open API Keys';
