-- Entity: App\Entity\MerchantBankAccount
-- Description: Merchant bank accounts for payouts

CREATE TABLE `merchant_bank_accounts` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_id` VARCHAR(26) NOT NULL,
    `bank_name` VARCHAR(100) NOT NULL COMMENT 'Bank name',
    `bank_code` VARCHAR(20) NULL COMMENT 'Bank code (SWIFT etc.)',
    `branch_name` VARCHAR(200) NULL COMMENT 'Branch name',
    `account_number` VARCHAR(50) NOT NULL COMMENT 'Bank account number',
    `account_holder` VARCHAR(100) NOT NULL COMMENT 'Account holder name',
    `account_type` VARCHAR(20) NOT NULL DEFAULT 'corporate' COMMENT 'corporate, personal',
    `currency` VARCHAR(3) NOT NULL DEFAULT 'CNY',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'active, pending, disabled',
    `is_default` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Is default account',
    `verified_at` DATETIME NULL COMMENT 'Verification time',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_mba_merchant` (`merchant_id`),
    INDEX `idx_mba_default` (`merchant_id`, `is_default`),
    CONSTRAINT `fk_mba_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Merchant bank accounts';
