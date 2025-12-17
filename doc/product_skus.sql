-- Entity: App\Entity\ProductSku
-- Description: Product SKUs (specific variants with size/color/specs)

CREATE TABLE `product_skus` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `product_id` VARCHAR(26) NOT NULL,
    `sku_code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique SKU code',
    `barcode` VARCHAR(50) NULL,
    `color_code` VARCHAR(20) NULL,
    `color_name` VARCHAR(50) NULL,
    `size_value` VARCHAR(20) NULL,
    `size_name` VARCHAR(50) NULL,
    `spec_info` JSON NULL COMMENT 'Additional specifications',
    `base_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Base/cost price',
    `weight` DECIMAL(8,2) NULL COMMENT 'Weight in grams',
    `length` DECIMAL(8,2) NULL COMMENT 'Length in cm',
    `width` DECIMAL(8,2) NULL COMMENT 'Width in cm',
    `height` DECIMAL(8,2) NULL COMMENT 'Height in cm',
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_sku_product` (`product_id`),
    INDEX `idx_sku_code` (`sku_code`),
    INDEX `idx_sku_barcode` (`barcode`),
    INDEX `idx_sku_active` (`is_active`),
    UNIQUE INDEX `uniq_product_variant` (`product_id`, `color_code`, `size_value`),
    CONSTRAINT `fk_sku_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product SKUs';