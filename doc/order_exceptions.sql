-- 订单异常工单表
-- 用于记录订单校验失败时创建的异常记录

CREATE TABLE `order_exceptions` (
    `id` VARCHAR(26) NOT NULL COMMENT 'ULID',
    `exception_no` VARCHAR(32) NOT NULL COMMENT '异常单号，如：OE20231217XXXXXXXX',
    `order_id` VARCHAR(26) NOT NULL COMMENT '关联订单',
    `type` VARCHAR(30) NOT NULL COMMENT '异常类型: inventory_insufficient|price_below_platform|product_not_matched|other',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT '状态: pending|resolved|closed',
    `description` TEXT NOT NULL COMMENT '异常描述',
    `details` JSON NULL COMMENT '异常详情（JSON），如：{skuId, required, available}',

    -- 处理信息
    `resolution` VARCHAR(30) NULL COMMENT '处理方式: confirm|cancel|adjusted',
    `resolution_notes` TEXT NULL COMMENT '处理说明',
    `resolved_by` VARCHAR(26) NULL COMMENT '处理人ID',
    `resolved_at` DATETIME NULL COMMENT '处理时间',

    `created_at` DATETIME NOT NULL COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL COMMENT '更新时间',

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_exception_no` (`exception_no`),
    KEY `idx_order_exc_order` (`order_id`),
    KEY `idx_order_exc_status` (`status`),
    KEY `idx_order_exc_type` (`type`),
    KEY `idx_order_exc_created` (`created_at`),
    CONSTRAINT `fk_order_exception_order` FOREIGN KEY (`order_id`)
        REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单异常工单';
