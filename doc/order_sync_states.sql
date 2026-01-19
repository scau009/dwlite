-- 订单同步状态表
-- 独立管理订单与渠道的同步状态，与 Order 一对一关联

CREATE TABLE `order_sync_states` (
    `id` VARCHAR(26) NOT NULL COMMENT 'ULID',
    `order_id` VARCHAR(26) NOT NULL COMMENT '关联订单',

    -- 一次性操作标记
    `is_pulled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已拉取创建',
    `is_confirmed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已确认推送',
    `is_cancelled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已取消推送',

    -- 发货同步追踪（可多次）
    `shipped_sync_count` INT NOT NULL DEFAULT 0 COMMENT '发货同步次数',
    `last_shipped_sync_at` DATETIME NULL COMMENT '最后发货同步时间',

    -- 补偿扫描
    `pending_operation` VARCHAR(20) NULL COMMENT '待执行操作: confirm|ship|cancel',

    -- 重试相关
    `retry_count` INT NOT NULL DEFAULT 0 COMMENT '当前操作重试次数',
    `last_attempt_at` DATETIME NULL COMMENT '最后尝试时间',
    `last_error_code` VARCHAR(50) NULL COMMENT '最后错误码',
    `last_error_message` VARCHAR(500) NULL COMMENT '最后错误信息',

    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order` (`order_id`),
    KEY `idx_pending_op` (`pending_operation`, `retry_count`, `last_attempt_at`),
    CONSTRAINT `fk_order_sync_state_order` FOREIGN KEY (`order_id`)
        REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单同步状态';
