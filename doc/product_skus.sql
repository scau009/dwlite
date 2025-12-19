-- Entity: App\Entity\ProductSku
-- Description: Product SKUs (specific variants with size/color/specs)

CREATE TABLE `product_skus` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `product_id` VARCHAR(26) NOT NULL,
    `sku_code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique SKU code, e.g. DR-2024SS-001-S-RED',
    `color_code` VARCHAR(20) NULL COMMENT 'Color code, e.g. RED, BLU, BLK',
    `size_unit` VARCHAR(20) NULL COMMENT 'Size unit, e.g. EU, US, CM',
    `size_value` VARCHAR(20) NULL COMMENT 'Size value, e.g. S, M, L, 38, 39, 40',
    `spec_info` JSON NULL COMMENT 'Spec summary, e.g. {"颜色": "红色", "尺码": "S"}',
    `price` DECIMAL(10,2) NOT NULL COMMENT 'Reference price',
    `original_price` DECIMAL(10,2) NULL COMMENT 'Original/tag price',
    `cost_price` DECIMAL(10,2) NULL COMMENT 'Cost price (optional)',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT 'Display order',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_sku_product` (`product_id`),
    INDEX `idx_sku_code` (`sku_code`),
    INDEX `idx_sku_active` (`is_active`),
    CONSTRAINT `fk_sku_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product SKUs';