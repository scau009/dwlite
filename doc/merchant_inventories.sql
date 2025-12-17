-- Entity: App\Entity\MerchantInventory
-- Description: Merchant inventory in warehouses

CREATE TABLE `merchant_inventories` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_id` VARCHAR(26) NOT NULL,
    `product_sku_id` VARCHAR(26) NOT NULL,
    `warehouse_id` VARCHAR(26) NOT NULL,
    `available_quantity` INT NOT NULL DEFAULT 0 COMMENT 'Available for sale',
    `reserved_quantity` INT NOT NULL DEFAULT 0 COMMENT 'Reserved for orders',
    `damaged_quantity` INT NOT NULL DEFAULT 0 COMMENT 'Damaged/defective',
    `total_quantity` INT NOT NULL DEFAULT 0 COMMENT 'Total physical stock',
    `cost_price` DECIMAL(10,2) NULL COMMENT 'Average cost price',
    `batch_info` JSON NULL COMMENT 'Batch tracking data',
    `last_inbound_at` DATETIME NULL COMMENT 'Last inbound time',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_mi_merchant` (`merchant_id`),
    INDEX `idx_mi_sku` (`product_sku_id`),
    INDEX `idx_mi_warehouse` (`warehouse_id`),
    INDEX `idx_mi_available` (`available_quantity`),
    UNIQUE INDEX `uniq_merchant_sku_warehouse` (`merchant_id`, `product_sku_id`, `warehouse_id`),
    CONSTRAINT `fk_mi_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mi_sku` FOREIGN KEY (`product_sku_id`) REFERENCES `product_skus` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mi_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Merchant inventories';