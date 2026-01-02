-- Entity: App\Entity\SalesChannel
-- Description: Sales channels (e.g., Taobao, JD, Douyin)

CREATE TABLE `sales_channels` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Channel code (taobao, jd, douyin, etc.)',
    `name` VARCHAR(100) NOT NULL,
    `logo_url` VARCHAR(500) NULL,
    `description` TEXT NULL,
    `config` JSON NULL COMMENT 'Channel global config',
    `config_schema` JSON NULL COMMENT 'JSON Schema for merchant config fields',
    `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, maintenance, disabled',
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_channel_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sales channels';
