-- Entity: App\Entity\RefreshToken
-- Description: JWT refresh tokens

CREATE TABLE `refresh_tokens` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `token` VARCHAR(128) NOT NULL UNIQUE,
    `user_id` VARCHAR(26) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL,
    `revoked` BOOLEAN NOT NULL DEFAULT FALSE,
    INDEX `idx_refresh_token` (`token`),
    INDEX `idx_refresh_token_expires` (`expires_at`),
    INDEX `idx_rt_user` (`user_id`),
    INDEX `idx_rt_revoked` (`revoked`),
    CONSTRAINT `fk_rt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Refresh tokens';