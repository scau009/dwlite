-- Entity: App\Entity\MerchantSalesChannel
-- Description: Merchant-SalesChannel connections

CREATE TABLE `merchant_sales_channels` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_id` VARCHAR(26) NOT NULL,
    `sales_channel_id` VARCHAR(26) NOT NULL,
    `fulfillment_type` VARCHAR(20) NOT NULL COMMENT 'consignment (寄售-送仓实物库存), self_fulfillment (自履约-虚拟库存)',
    `pricing_model` VARCHAR(20) NOT NULL DEFAULT 'self_pricing' COMMENT 'self_pricing (自主定价), platform_managed (平台托管定价)',
    `config` JSON NULL COMMENT 'Merchant config for this channel (shop ID, API keys, etc.)',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, active, suspended, disabled',
    `approved_at` DATETIME NULL COMMENT 'Approval time',
    `approved_by` VARCHAR(26) NULL COMMENT 'Approver user ID',
    `remark` VARCHAR(255) NULL COMMENT 'Remark or rejection reason',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE INDEX `uk_merchant_channel` (`merchant_id`, `sales_channel_id`),
    INDEX `idx_msc_merchant` (`merchant_id`),
    INDEX `idx_msc_channel` (`sales_channel_id`),
    CONSTRAINT `fk_msc_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_msc_channel` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Merchant-SalesChannel connections';


-- 1. 添加新字段
ALTER TABLE `merchant_sales_channels`
    ADD COLUMN `fulfillment_type` VARCHAR(20) NULL COMMENT 'consignment (寄售-送仓实物库存), self_fulfillment (自履约-虚拟库存)' AFTER `sales_channel_id`,
    ADD COLUMN `pricing_model` VARCHAR(20) NULL DEFAULT 'self_pricing' COMMENT 'self_pricing (自主定价), platform_managed (平台托管定价)' AFTER `fulfillment_type`;
