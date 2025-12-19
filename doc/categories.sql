-- Entity: App\Entity\Category
-- Description: Product categories (hierarchical tree structure)

CREATE TABLE `categories` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `parent_id` VARCHAR(26) NULL COMMENT 'Parent category ID',
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(120) NOT NULL UNIQUE COMMENT 'URL-friendly identifier',
    `description` TEXT NULL,
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT 'Display order, lower first',
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_category_slug` (`slug`),
    INDEX `idx_category_parent` (`parent_id`),
    CONSTRAINT `fk_category_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categories';