-- Entity: App\Entity\ApiKeyLog
-- Description: API call audit logs

CREATE TABLE `api_key_logs` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `api_key_id` VARCHAR(26) NOT NULL COMMENT 'FK to api_keys',
    `endpoint` VARCHAR(255) NOT NULL COMMENT 'Called endpoint path',
    `method` VARCHAR(10) NOT NULL COMMENT 'HTTP method',
    `request_id` VARCHAR(36) NOT NULL COMMENT 'Request trace ID',
    `ip_address` VARCHAR(45) NOT NULL COMMENT 'Client IP address',
    `user_agent` VARCHAR(500) NULL COMMENT 'Client user agent',
    `request_body_size` INT UNSIGNED NULL COMMENT 'Request body size in bytes',
    `status_code` SMALLINT UNSIGNED NOT NULL COMMENT 'HTTP response status code',
    `error_code` VARCHAR(50) NULL COMMENT 'Error code if failed',
    `response_time_ms` INT UNSIGNED NOT NULL COMMENT 'Response time in milliseconds',
    `created_at` DATETIME NOT NULL COMMENT 'Timestamp',
    INDEX `idx_api_key_log_api_key` (`api_key_id`),
    INDEX `idx_api_key_log_request_id` (`request_id`),
    INDEX `idx_api_key_log_created_at` (`created_at`),
    INDEX `idx_api_key_log_endpoint` (`endpoint`(100)),
    INDEX `idx_api_key_log_status_code` (`status_code`),
    CONSTRAINT `fk_api_key_log_api_key` FOREIGN KEY (`api_key_id`) REFERENCES `api_keys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API Call Audit Logs';
