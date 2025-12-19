-- Entity: App\Entity\Product
-- Description: Products (SPU level)

CREATE TABLE `products` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `brand_id` VARCHAR(26) NULL,
    `category_id` VARCHAR(26) NULL,
    `name` VARCHAR(200) NOT NULL,
    `slug` VARCHAR(220) NOT NULL UNIQUE,
    `style_number` VARCHAR(50) NOT NULL COMMENT '款号',
    `season` VARCHAR(20) NOT NULL COMMENT '季节: 2024SS, 2024AW, 2024FW',
    `color` VARCHAR(50) NULL COMMENT '颜色名',
    `description` TEXT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'draft, active, inactive, discontinued',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_product_slug` (`slug`),
    INDEX `idx_product_style_number` (`style_number`),
    INDEX `idx_product_season` (`season`),
    INDEX `idx_product_brand` (`brand_id`),
    INDEX `idx_product_category` (`category_id`),
    INDEX `idx_product_active` (`is_active`),
    INDEX `idx_product_status` (`status`),
    INDEX `idx_product_name` (`name`),
    CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_product_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Products (SPU)';

-- Product Tags (Many-to-Many)
CREATE TABLE `product_tags` (
    `product_id` VARCHAR(26) NOT NULL,
    `tag_id` VARCHAR(26) NOT NULL,
    PRIMARY KEY (`product_id`, `tag_id`),
    CONSTRAINT `fk_product_tags_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_product_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product-Tag associations';
