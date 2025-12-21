-- Entity: App\Entity\WalletTransaction
-- Description: Wallet transaction records

CREATE TABLE `wallet_transactions` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `transaction_no` VARCHAR(20) NOT NULL UNIQUE COMMENT 'Business number: WT2024121900001',
    `wallet_id` VARCHAR(26) NOT NULL,
    `type` VARCHAR(20) NOT NULL COMMENT 'credit, debit, freeze, unfreeze',
    `amount` DECIMAL(12,2) NOT NULL COMMENT 'Transaction amount',
    `balance_before` DECIMAL(12,2) NOT NULL COMMENT 'Balance before transaction',
    `balance_after` DECIMAL(12,2) NOT NULL COMMENT 'Balance after transaction',
    `biz_type` VARCHAR(50) NOT NULL COMMENT 'Business type: deposit_charge, order_income, withdraw, etc.',
    `biz_id` VARCHAR(26) NULL COMMENT 'Related business entity ID',
    `remark` VARCHAR(255) NULL COMMENT 'Transaction remark',
    `operator_id` VARCHAR(26) NULL COMMENT 'Who performed the action',
    `created_at` DATETIME NOT NULL,
    INDEX `idx_wallet_created` (`wallet_id`, `created_at`),
    INDEX `idx_biz` (`biz_type`, `biz_id`),
    INDEX `idx_transaction_no` (`transaction_no`),
    CONSTRAINT `fk_wt_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Wallet transactions';
