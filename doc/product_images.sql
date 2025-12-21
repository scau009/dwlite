-- Entity: App\Entity\ProductImage
-- Description: Product images with COS storage

CREATE TABLE `product_images` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `product_id` VARCHAR(26) NOT NULL,
    `cos_key` VARCHAR(255) NOT NULL COMMENT 'COS object key, e.g. products/2024/01/abc123.jpg',
    `url` VARCHAR(500) NOT NULL COMMENT 'Full CDN URL',
    `thumbnail_url` VARCHAR(500) NULL COMMENT 'Thumbnail URL (COS image processing)',
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_primary` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Primary/cover image',
    `file_size` INT NULL COMMENT 'File size in bytes',
    `width` INT NULL COMMENT 'Image width in pixels',
    `height` INT NULL COMMENT 'Image height in pixels',
    `created_at` DATETIME NOT NULL,
    INDEX `idx_image_product` (`product_id`),
    INDEX `idx_image_cos_key` (`cos_key`),
    CONSTRAINT `fk_image_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product images';
