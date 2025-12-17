-- Entity: App\Entity\EmailVerificationToken
-- Description: Email verification tokens for user registration

CREATE TABLE `email_verification_tokens` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `user_id` VARCHAR(26) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_evt_token` (`token`),
    INDEX `idx_evt_user` (`user_id`),
    INDEX `idx_evt_expires` (`expires_at`),
    CONSTRAINT `fk_evt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Email verification tokens';