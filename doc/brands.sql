-- Entity: App\Entity\Brand
-- Description: Product brands

CREATE TABLE `brands` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(120) NOT NULL UNIQUE COMMENT 'URL-friendly identifier',
    `logo_url` VARCHAR(500) NULL COMMENT 'Logo URL',
    `description` TEXT NULL,
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT 'Display order, lower first',
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_brand_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Brands';