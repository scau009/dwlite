-- 规则执行日志表
-- 记录规则执行的输入、输出和性能数据，用于调试和审计

CREATE TABLE `rule_execution_logs` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',

    -- 执行的规则
    `rule_type` VARCHAR(20) NOT NULL COMMENT '规则类型: merchant(商户规则), platform(平台规则)',
    `rule_id` VARCHAR(26) NOT NULL COMMENT '规则ID',

    -- 执行上下文
    `context_type` VARCHAR(50) NOT NULL COMMENT '执行场景: pricing(定价), stock_allocation(库存分配), settlement(结算)',
    `context_id` VARCHAR(26) NULL COMMENT '相关实体ID(如 ChannelProduct ID, InventoryListing ID)',

    -- 执行数据
    `input_data` JSON NOT NULL COMMENT '输入变量',
    `output_value` VARCHAR(255) NULL COMMENT '计算结果',
    `execution_time_ms` INT NOT NULL COMMENT '执行耗时(毫秒)',

    -- 执行状态
    `success` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否成功',
    `error_message` TEXT NULL COMMENT '失败原因',

    -- 审计字段
    `created_at` DATETIME NOT NULL COMMENT '创建时间',

    -- 索引
    INDEX `idx_rel_rule` (`rule_type`, `rule_id`),
    INDEX `idx_rel_context` (`context_type`, `context_id`),
    INDEX `idx_rel_created` (`created_at`),
    INDEX `idx_rel_success` (`success`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='规则执行日志表';
