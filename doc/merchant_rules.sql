-- 商户规则定义表
-- 商户自己配置的定价规则和库存分配规则

CREATE TABLE `merchant_rules` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_id` VARCHAR(26) NOT NULL COMMENT '所属商户ID',

    -- 基本信息
    `code` VARCHAR(100) NOT NULL COMMENT '规则编码(商户内唯一)',
    `name` VARCHAR(200) NOT NULL COMMENT '规则名称',
    `description` TEXT NULL COMMENT '规则描述',

    -- 规则分类
    `type` VARCHAR(50) NOT NULL COMMENT '规则类型: pricing(定价规则), stock_allocation(库存分配规则)',
    `category` VARCHAR(50) NOT NULL COMMENT '规则分类: markup(加价), discount(折扣), ratio(比例), limit(上限)',

    -- 表达式
    `expression` TEXT NOT NULL COMMENT '主表达式(Symfony Expression Language)',
    `condition_expression` TEXT NULL COMMENT '条件表达式(满足条件才执行主表达式)',

    -- 配置
    `priority` INT NOT NULL DEFAULT 0 COMMENT '优先级(越小越高)',
    `config` JSON NULL COMMENT '配置参数',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用',

    -- 审计字段
    `created_at` DATETIME NOT NULL COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL COMMENT '更新时间',

    -- 索引
    UNIQUE INDEX `uniq_merchant_rule_code` (`merchant_id`, `code`),
    INDEX `idx_mr_merchant` (`merchant_id`),
    INDEX `idx_mr_type` (`type`),
    INDEX `idx_mr_active` (`is_active`),

    -- 外键
    CONSTRAINT `fk_mr_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商户规则定义表';
