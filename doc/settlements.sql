-- Entity: App\Entity\Settlement
-- Description: T+N delayed settlement records (created when fulfillment completes)

CREATE TABLE `settlements` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `settlement_no` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Settlement number: ST2024121900001',
    `merchant_id` VARCHAR(26) NOT NULL,
    `fulfillment_id` VARCHAR(26) NOT NULL UNIQUE COMMENT 'One-to-one with fulfillment',
    `order_id` VARCHAR(26) NOT NULL,
    `gross_amount` DECIMAL(12,2) NOT NULL COMMENT 'Settlement amount before commission',
    `commission_rate` DECIMAL(5,2) NOT NULL COMMENT 'Commission rate (e.g. 5.00 = 5%)',
    `commission_amount` DECIMAL(12,2) NOT NULL COMMENT 'Commission amount',
    `net_amount` DECIMAL(12,2) NOT NULL COMMENT 'Net amount (gross - commission)',
    `currency` VARCHAR(3) NOT NULL DEFAULT 'CNY',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, settled, cancelled',
    `settlement_days` INT NOT NULL DEFAULT 7 COMMENT 'T+N days delay',
    `scheduled_settle_at` DATETIME NOT NULL COMMENT 'Scheduled settlement time',
    `settled_at` DATETIME NULL COMMENT 'Actual settlement time',
    `cancelled_at` DATETIME NULL COMMENT 'Cancellation time',
    `cancel_reason` VARCHAR(255) NULL COMMENT 'Cancellation reason',
    `wallet_transaction_id` VARCHAR(26) NULL COMMENT 'Wallet transaction ID when settled',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_settlement_merchant` (`merchant_id`),
    INDEX `idx_settlement_fulfillment` (`fulfillment_id`),
    INDEX `idx_settlement_status` (`status`),
    INDEX `idx_settlement_scheduled` (`scheduled_settle_at`),
    INDEX `idx_settlement_no` (`settlement_no`),
    CONSTRAINT `fk_settlement_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`),
    CONSTRAINT `fk_settlement_fulfillment` FOREIGN KEY (`fulfillment_id`) REFERENCES `fulfillments` (`id`),
    CONSTRAINT `fk_settlement_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Settlements';
