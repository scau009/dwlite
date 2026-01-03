-- Entity: App\Entity\InventoryListing
-- Description: Merchant inventory listings for sales channels

CREATE TABLE `inventory_listings` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_inventory_id` VARCHAR(26) NOT NULL,
    `merchant_sales_channel_id` VARCHAR(26) NOT NULL,
    `allocation_mode` VARCHAR(20) NOT NULL DEFAULT 'shared' COMMENT 'shared (半托管-共享库存), dedicated (全托管-独占库存)',
    `allocated_quantity` INT NULL COMMENT 'Allocated quantity for dedicated mode',
    `sold_quantity` INT NOT NULL DEFAULT 0 COMMENT 'Sold quantity via this listing',
    `fulfillment_type` VARCHAR(20) NOT NULL DEFAULT 'consignment' COMMENT 'consignment (寄售), self_fulfillment (自履约)',
    `pricing_model` VARCHAR(20) NOT NULL DEFAULT 'self_pricing' COMMENT 'self_pricing (自主定价), platform_managed (平台托管)',
    `price` DECIMAL(10,2) NOT NULL COMMENT 'Merchant price (for self_pricing)',
    `compare_at_price` DECIMAL(10,2) NULL COMMENT 'Compare at price / original price',
    `status` VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'draft, active, paused, sold_out',
    `remark` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE INDEX `uniq_inventory_channel` (`merchant_inventory_id`, `merchant_sales_channel_id`),
    INDEX `idx_listing_inventory` (`merchant_inventory_id`),
    INDEX `idx_listing_channel` (`merchant_sales_channel_id`),
    INDEX `idx_listing_status` (`status`),
    INDEX `idx_listing_fulfillment` (`fulfillment_type`),
    INDEX `idx_listing_pricing` (`pricing_model`),
    CONSTRAINT `fk_listing_inventory` FOREIGN KEY (`merchant_inventory_id`) REFERENCES `merchant_inventories` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_listing_channel` FOREIGN KEY (`merchant_sales_channel_id`) REFERENCES `merchant_sales_channels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Merchant inventory listings for sales channels';


-- Migration from old schema (run manually):
-- 1. Add new columns
ALTER TABLE `inventory_listings`
    ADD COLUMN `fulfillment_type` VARCHAR(20) NOT NULL DEFAULT 'consignment' COMMENT 'consignment (寄售), self_fulfillment (自履约)' AFTER `sold_quantity`,
    ADD COLUMN `pricing_model` VARCHAR(20) NOT NULL DEFAULT 'self_pricing' COMMENT 'self_pricing (自主定价), platform_managed (平台托管)' AFTER `fulfillment_type`;

-- 2. Add indexes
ALTER TABLE `inventory_listings`
    ADD INDEX `idx_listing_fulfillment` (`fulfillment_type`),
    ADD INDEX `idx_listing_pricing` (`pricing_model`);