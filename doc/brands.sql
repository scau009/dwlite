-- Entity: App\Entity\Brand
-- Description: Product brands

CREATE TABLE `brands` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `name` VARCHAR(100) NOT NULL,
    `name_en` VARCHAR(100) NULL COMMENT 'English name',
    `logo` VARCHAR(500) NULL COMMENT 'Logo URL',
    `description` TEXT NULL,
    `website` VARCHAR(255) NULL,
    `country` VARCHAR(50) NULL COMMENT 'Country of origin',
    `display_order` INT NOT NULL DEFAULT 0,
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE INDEX `uniq_brand_name` (`name`),
    INDEX `idx_brand_active` (`is_active`),
    INDEX `idx_brand_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Brands';