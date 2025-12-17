-- Entity: App\Entity\ProductImage
-- Description: Product images

CREATE TABLE `product_images` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `product_id` VARCHAR(26) NOT NULL,
    `url` VARCHAR(500) NOT NULL COMMENT 'Image URL',
    `alt_text` VARCHAR(255) NULL,
    `display_order` INT NOT NULL DEFAULT 0,
    `is_primary` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Primary/cover image',
    `created_at` DATETIME NOT NULL,
    INDEX `idx_image_product` (`product_id`),
    INDEX `idx_image_primary` (`is_primary`),
    INDEX `idx_image_order` (`display_order`),
    CONSTRAINT `fk_image_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product images';