-- Entity: App\Entity\InventoryListing
-- Description: Inventory listings available for allocation (published to marketplace)

CREATE TABLE `inventory_listings` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_inventory_id` VARCHAR(26) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL COMMENT 'Listed price',
    `available_quantity` INT NOT NULL DEFAULT 0 COMMENT 'Available for allocation',
    `min_order_quantity` INT NOT NULL DEFAULT 1,
    `max_order_quantity` INT NULL,
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `listed_at` DATETIME NULL COMMENT 'When made available',
    `delisted_at` DATETIME NULL COMMENT 'When removed from market',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_il_merchant_inventory` (`merchant_inventory_id`),
    INDEX `idx_il_active` (`is_active`),
    INDEX `idx_il_price` (`price`),
    INDEX `idx_il_available` (`available_quantity`),
    CONSTRAINT `fk_il_merchant_inventory` FOREIGN KEY (`merchant_inventory_id`) REFERENCES `merchant_inventories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory listings';