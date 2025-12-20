-- Entity: App\Entity\ProductSku
-- Description: Product SKUs (specific variants with size info)

CREATE TABLE `product_skus` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `product_id` VARCHAR(26) NOT NULL,
    `size_unit` ENUM('EU', 'US', 'UK', 'CM') NULL COMMENT 'Size unit: EU, US, UK, CM',
    `size_value` VARCHAR(20) NULL COMMENT 'Size value, e.g. S, M, L, 38, 39, 40',
    `spec_info` JSON NULL COMMENT 'Spec summary, e.g. {"颜色": "红色", "尺码": "S"}',
    `price` DECIMAL(10,2) NOT NULL COMMENT 'Reference price (参考价)',
    `original_price` DECIMAL(10,2) NULL COMMENT 'Release price (发售价)',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT 'Display order',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_sku_product` (`product_id`),
    INDEX `idx_sku_active` (`is_active`),
    CONSTRAINT `fk_sku_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product SKUs';