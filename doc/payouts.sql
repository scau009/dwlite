-- Entity: App\Entity\Payout
-- Description: Merchant payout/withdrawal requests

CREATE TABLE `payouts` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `payout_no` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Payout number: WD2024121900001',
    `merchant_id` VARCHAR(26) NOT NULL,
    `bank_account_id` VARCHAR(26) NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL COMMENT 'Requested amount',
    `fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Processing fee',
    `actual_amount` DECIMAL(12,2) NOT NULL COMMENT 'Actual amount (amount - fee)',
    `currency` VARCHAR(3) NOT NULL DEFAULT 'CNY',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, approved, processing, completed, rejected, failed',
    -- Bank account snapshot (for historical reference)
    `bank_name` VARCHAR(100) NOT NULL COMMENT 'Bank name snapshot',
    `account_number` VARCHAR(50) NOT NULL COMMENT 'Account number snapshot',
    `account_holder` VARCHAR(100) NOT NULL COMMENT 'Account holder snapshot',
    `bank_code` VARCHAR(20) NULL COMMENT 'Bank code snapshot',
    -- Timestamps
    `approved_at` DATETIME NULL COMMENT 'Approval time',
    `processing_at` DATETIME NULL COMMENT 'Processing start time',
    `completed_at` DATETIME NULL COMMENT 'Completion time',
    `rejected_at` DATETIME NULL COMMENT 'Rejection time',
    `failed_at` DATETIME NULL COMMENT 'Failure time',
    -- Review fields
    `reviewed_by` VARCHAR(26) NULL COMMENT 'Reviewer ID',
    `reject_reason` VARCHAR(255) NULL COMMENT 'Rejection reason',
    `fail_reason` VARCHAR(255) NULL COMMENT 'Failure reason',
    `remark` TEXT NULL COMMENT 'Internal remark',
    -- External references
    `external_transaction_id` VARCHAR(100) NULL COMMENT 'Bank/payment gateway transaction ID',
    `wallet_transaction_id` VARCHAR(26) NULL COMMENT 'Wallet debit transaction ID',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_payout_merchant` (`merchant_id`),
    INDEX `idx_payout_status` (`status`),
    INDEX `idx_payout_no` (`payout_no`),
    INDEX `idx_payout_bank_account` (`bank_account_id`),
    CONSTRAINT `fk_payout_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`),
    CONSTRAINT `fk_payout_bank_account` FOREIGN KEY (`bank_account_id`) REFERENCES `merchant_bank_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payouts';
