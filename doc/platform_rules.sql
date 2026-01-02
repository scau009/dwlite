-- 平台规则定义表
-- 平台管理员配置的加价规则、库存优先级规则、结算费率规则

CREATE TABLE `platform_rules` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',

    -- 基本信息
    `code` VARCHAR(100) NOT NULL UNIQUE COMMENT '规则编码(全局唯一)',
    `name` VARCHAR(200) NOT NULL COMMENT '规则名称',
    `description` TEXT NULL COMMENT '规则描述',

    -- 规则分类
    `type` VARCHAR(50) NOT NULL COMMENT '规则类型: pricing(加价规则), stock_priority(库存优先级), settlement_fee(结算费率)',
    `category` VARCHAR(50) NOT NULL COMMENT '规则分类: markup(加价), discount(折扣), priority(优先级), fee_rate(费率)',

    -- 表达式
    `expression` TEXT NOT NULL COMMENT '主表达式(Symfony Expression Language)',
    `condition_expression` TEXT NULL COMMENT '条件表达式(满足条件才执行主表达式)',

    -- 配置
    `priority` INT NOT NULL DEFAULT 0 COMMENT '优先级(越小越高)',
    `config` JSON NULL COMMENT '配置参数',
    `is_system` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否系统规则(不可删除)',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用',

    -- 审计字段
    `created_by` VARCHAR(26) NULL COMMENT '创建人ID',
    `created_at` DATETIME NOT NULL COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL COMMENT '更新时间',

    -- 索引
    INDEX `idx_pr_type` (`type`),
    INDEX `idx_pr_category` (`category`),
    INDEX `idx_pr_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='平台规则定义表';
