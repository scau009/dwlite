-- Entity: App\Entity\Webhook
-- Description: Webhook subscription configurations

CREATE TABLE `webhooks` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `api_key_id` VARCHAR(26) NOT NULL COMMENT 'FK to api_keys',
    `url` VARCHAR(500) NOT NULL COMMENT 'Webhook endpoint URL',
    `events` JSON NOT NULL COMMENT 'Array of subscribed event types',
    `secret` VARCHAR(64) NOT NULL COMMENT 'Webhook signing secret (for HMAC)',
    `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, suspended, failed',
    `failure_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Consecutive failure count',
    `last_triggered_at` DATETIME NULL COMMENT 'Last trigger timestamp',
    `last_success_at` DATETIME NULL COMMENT 'Last successful delivery timestamp',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_webhook_api_key` (`api_key_id`),
    INDEX `idx_webhook_status` (`status`),
    CONSTRAINT `fk_webhook_api_key` FOREIGN KEY (`api_key_id`) REFERENCES `api_keys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Webhook Subscriptions';
