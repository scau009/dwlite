-- Entity: App\Entity\WalletTransaction
-- Description: Wallet transaction records

CREATE TABLE `wallet_transactions` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `wallet_id` VARCHAR(26) NOT NULL,
    `transaction_no` VARCHAR(32) NOT NULL UNIQUE COMMENT 'Transaction number',
    `type` VARCHAR(30) NOT NULL COMMENT 'settlement, withdrawal, refund, adjustment, etc.',
    `amount` DECIMAL(12,2) NOT NULL COMMENT 'Transaction amount (positive or negative)',
    `balance_before` DECIMAL(12,2) NOT NULL COMMENT 'Balance before transaction',
    `balance_after` DECIMAL(12,2) NOT NULL COMMENT 'Balance after transaction',
    `reference_type` VARCHAR(50) NULL COMMENT 'Related entity type',
    `reference_id` VARCHAR(26) NULL COMMENT 'Related entity ID',
    `description` VARCHAR(255) NULL,
    `operator_id` VARCHAR(26) NULL COMMENT 'Who performed the action',
    `created_at` DATETIME NOT NULL,
    INDEX `idx_wt_wallet` (`wallet_id`),
    INDEX `idx_wt_no` (`transaction_no`),
    INDEX `idx_wt_type` (`type`),
    INDEX `idx_wt_reference` (`reference_type`, `reference_id`),
    INDEX `idx_wt_created` (`created_at`),
    CONSTRAINT `fk_wt_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Wallet transactions';