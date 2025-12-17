-- Entity: App\Entity\Tag
-- Description: Product tags for categorization and filtering

CREATE TABLE `tags` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `slug` VARCHAR(50) NOT NULL UNIQUE COMMENT 'URL-friendly identifier',
    `description` VARCHAR(255) NULL,
    `color` VARCHAR(7) NULL COMMENT 'Hex color code',
    `created_at` DATETIME NOT NULL,
    INDEX `idx_tag_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tags';