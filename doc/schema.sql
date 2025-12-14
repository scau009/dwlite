-- DWLite Authentication Schema
-- Run this SQL in your MySQL database to create the required tables

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
    `id` VARCHAR(26) NOT NULL,
    `email` VARCHAR(180) NOT NULL,
    `roles` JSON NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    `updated_at` DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY (`id`),
    UNIQUE INDEX `UNIQ_1483A5E9E7927C74` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email verification tokens table
CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
    `id` VARCHAR(26) NOT NULL,
    `user_id` VARCHAR(26) NOT NULL,
    `token` VARCHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    `created_at` DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY (`id`),
    UNIQUE INDEX `UNIQ_3DCFD5D5F37A13B` (`token`),
    INDEX `IDX_3DCFD5DA76ED395` (`user_id`),
    CONSTRAINT `FK_email_verification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password reset tokens table
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `id` VARCHAR(26) NOT NULL,
    `user_id` VARCHAR(26) NOT NULL,
    `token` VARCHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    `created_at` DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY (`id`),
    UNIQUE INDEX `UNIQ_3967A2DD5F37A13B` (`token`),
    INDEX `IDX_3967A2DDA76ED395` (`user_id`),
    CONSTRAINT `FK_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Refresh tokens table (for gesdinet/jwt-refresh-token-bundle)
CREATE TABLE IF NOT EXISTS `refresh_tokens` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `refresh_token` VARCHAR(128) NOT NULL,
    `username` VARCHAR(255) NOT NULL,
    `valid` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `UNIQ_9BACE7E1C74F2195` (`refresh_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE refresh_tokens (
                                id VARCHAR(26) NOT NULL PRIMARY KEY,
                                token VARCHAR(128) NOT NULL,
                                user_id VARCHAR(26) NOT NULL,
                                expires_at DATETIME NOT NULL,
                                created_at DATETIME NOT NULL,
                                revoked TINYINT(1) NOT NULL DEFAULT 0,
                                UNIQUE INDEX UNIQ_9BACE7E15F37A13B (token),
                                INDEX IDX_9BACE7E1A76ED395 (user_id),
                                INDEX idx_refresh_token (token),
                                INDEX idx_refresh_token_expires (expires_at),
                                CONSTRAINT FK_9BACE7E1A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;