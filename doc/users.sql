-- Entity: App\Entity\User
-- Description: User accounts with email/password authentication

CREATE TABLE `users` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `email` VARCHAR(180) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `roles` JSON NOT NULL COMMENT 'User roles array',
    `is_verified` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Email verification status',
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_user_email` (`email`),
    INDEX `idx_user_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User accounts';