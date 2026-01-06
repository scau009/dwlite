-- Entity: App\Entity\MerchantSalesChannel
-- Description: Merchant-SalesChannel connections

CREATE TABLE `merchant_sales_channels` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_id` VARCHAR(26) NOT NULL,
    `sales_channel_id` VARCHAR(26) NOT NULL,
    `requested_fulfillment_types` JSON NOT NULL COMMENT 'Requested by merchant: ["consignment", "self_fulfillment"]',
    `approved_fulfillment_types` JSON NULL COMMENT 'Approved by admin: ["consignment", "self_fulfillment"]',
    `config` JSON NULL COMMENT 'Merchant config for this channel (shop ID, API keys, etc.)',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, active, suspended, disabled, rejected',
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


-- Migration from old schema (run manually):
-- 1. Add new columns
ALTER TABLE `merchant_sales_channels`
    ADD COLUMN `requested_fulfillment_types` JSON NULL AFTER `sales_channel_id`,
    ADD COLUMN `approved_fulfillment_types` JSON NULL AFTER `requested_fulfillment_types`;

-- 2. Migrate data from old fulfillment_type column
UPDATE `merchant_sales_channels`
SET `requested_fulfillment_types` = JSON_ARRAY(`fulfillment_type`),
    `approved_fulfillment_types` = CASE
        WHEN `status` IN ('active', 'suspended', 'disabled') THEN JSON_ARRAY(`fulfillment_type`)
        ELSE NULL
    END WHERE status != '';

-- 3. Make requested_fulfillment_types NOT NULL
ALTER TABLE `merchant_sales_channels`
    MODIFY COLUMN `requested_fulfillment_types` JSON NOT NULL;

-- 4. Drop old columns
ALTER TABLE `merchant_sales_channels`
    DROP COLUMN `fulfillment_type`,
    DROP COLUMN `pricing_model`;
