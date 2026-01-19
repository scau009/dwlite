-- 订单同步日志表
-- 记录每次同步操作的详细信息

CREATE TABLE `order_sync_logs` (
    `id` VARCHAR(26) NOT NULL COMMENT 'ULID',
    `order_id` VARCHAR(26) NULL COMMENT '关联订单ID',
    `sales_channel_id` VARCHAR(26) NOT NULL COMMENT '销售渠道ID',
    `external_order_id` VARCHAR(100) NULL COMMENT '外部订单ID',

    `direction` VARCHAR(10) NOT NULL COMMENT '同步方向: pull|push',
    `operation` VARCHAR(50) NOT NULL COMMENT '操作类型: pull_order|confirm_order|ship_order|cancel_order',
    `status` VARCHAR(20) NOT NULL COMMENT '状态: pending|processing|success|failed|skipped',

    `request_data` JSON NULL COMMENT '请求数据',
    `response_data` JSON NULL COMMENT '响应数据',

    `error_code` VARCHAR(50) NULL COMMENT '错误码',
    `error_message` TEXT NULL COMMENT '错误信息',

    `retry_count` INT NOT NULL DEFAULT 0 COMMENT '重试次数',

    `started_at` DATETIME NOT NULL COMMENT '开始时间',
    `completed_at` DATETIME NULL COMMENT '完成时间',
    `duration_ms` INT UNSIGNED NULL COMMENT '耗时(毫秒)',

    `created_at` DATETIME NOT NULL,

    PRIMARY KEY (`id`),
    KEY `idx_order` (`order_id`),
    KEY `idx_sales_channel` (`sales_channel_id`),
    KEY `idx_external_order` (`external_order_id`),
    KEY `idx_direction_status` (`direction`, `status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单同步日志';
