-- Entity: App\Entity\Tag
-- Description: Product tags for categorization and filtering

CREATE TABLE `tags` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `name` VARCHAR(50) NOT NULL COMMENT 'Tag display name',
    `slug` VARCHAR(60) NOT NULL UNIQUE COMMENT 'URL-friendly identifier',
    `color` VARCHAR(7) NULL COMMENT 'Hex color code, e.g. #FF5733',
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT 'Display order',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Whether tag is active',
    `created_at` DATETIME NOT NULL,
    INDEX `idx_tag_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tags';