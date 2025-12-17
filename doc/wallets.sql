-- Entity: App\Entity\Wallet
-- Description: Merchant wallets for settlements

CREATE TABLE `wallets` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_id` VARCHAR(26) NOT NULL UNIQUE,
    `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Available balance',
    `frozen` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Frozen amount',
    `total_income` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Lifetime income',
    `total_withdrawn` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Lifetime withdrawals',
    `currency` VARCHAR(3) NOT NULL DEFAULT 'CNY',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_wallet_merchant` (`merchant_id`),
    CONSTRAINT `fk_wallet_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Merchant wallets';