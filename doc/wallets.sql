-- Entity: App\Entity\Wallet
-- Description: Merchant wallets for settlements

CREATE TABLE `wallets` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_id` VARCHAR(26) NOT NULL UNIQUE,
    `type` varchar(20) NOT NULL DEFAULT 'deposit' COMMENT 'deposit=保证金钱包,balance=余额钱包',
    `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Available balance',
    `frozen_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Frozen amount',
    `status` varchar(20) NOT NULL DEFAULT 'active' COMMENT 'active=正常,frozen=冻结',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_wallet_merchant` (`merchant_id`),
    CONSTRAINT `fk_wallet_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Merchant wallets';