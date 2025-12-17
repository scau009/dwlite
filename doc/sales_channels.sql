-- Entity: App\Entity\SalesChannel
-- Description: Sales channels (e.g., Taobao, JD, Douyin)

CREATE TABLE `sales_channels` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Channel code (taobao, jd, douyin, etc.)',
    `type` VARCHAR(30) NOT NULL COMMENT 'marketplace, social, retail, etc.',
    `platform` VARCHAR(50) NOT NULL COMMENT 'Platform name',
    `logo` VARCHAR(500) NULL,
    `description` TEXT NULL,
    `api_endpoint` VARCHAR(255) NULL COMMENT 'API base URL',
    `api_config` JSON NULL COMMENT 'API configuration',
    `webhook_config` JSON NULL COMMENT 'Webhook configuration',
    `sync_interval` INT NOT NULL DEFAULT 300 COMMENT 'Order sync interval (seconds)',
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_channel_code` (`code`),
    INDEX `idx_channel_type` (`type`),
    INDEX `idx_channel_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sales channels';