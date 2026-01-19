-- Entity: App\Entity\WebhookDelivery
-- Description: Webhook delivery logs and retry tracking

CREATE TABLE `webhook_deliveries` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `webhook_id` VARCHAR(26) NOT NULL COMMENT 'FK to webhooks',
    `event` VARCHAR(50) NOT NULL COMMENT 'Event type',
    `event_id` VARCHAR(36) NOT NULL COMMENT 'Unique event ID',
    `payload` JSON NOT NULL COMMENT 'Delivered payload',
    `response_code` SMALLINT UNSIGNED NULL COMMENT 'HTTP response code',
    `response_body` TEXT NULL COMMENT 'Response body (truncated to 2000 chars)',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, delivered, failed',
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Delivery attempts',
    `next_retry_at` DATETIME NULL COMMENT 'Next retry timestamp',
    `delivered_at` DATETIME NULL COMMENT 'Successful delivery timestamp',
    `created_at` DATETIME NOT NULL,
    INDEX `idx_webhook_delivery_webhook` (`webhook_id`),
    INDEX `idx_webhook_delivery_event_id` (`event_id`),
    INDEX `idx_webhook_delivery_status` (`status`),
    INDEX `idx_webhook_delivery_next_retry` (`next_retry_at`),
    INDEX `idx_webhook_delivery_created_at` (`created_at`),
    CONSTRAINT `fk_webhook_delivery_webhook` FOREIGN KEY (`webhook_id`) REFERENCES `webhooks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Webhook Delivery Logs';
