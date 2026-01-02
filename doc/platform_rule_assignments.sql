-- 平台规则分配表
-- 将平台规则分配到商户或渠道商品

CREATE TABLE `platform_rule_assignments` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `platform_rule_id` VARCHAR(26) NOT NULL COMMENT '平台规则ID',

    -- 分配范围(多态关联)
    `scope_type` VARCHAR(50) NOT NULL COMMENT '范围类型: merchant(商户), channel_product(渠道商品)',
    `scope_id` VARCHAR(26) NOT NULL COMMENT '对应实体的ID',

    -- 覆盖配置
    `priority_override` INT NULL COMMENT '覆盖规则的默认优先级',
    `config_override` JSON NULL COMMENT '覆盖规则的默认配置',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用此分配',

    -- 审计字段
    `created_at` DATETIME NOT NULL COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL COMMENT '更新时间',

    -- 索引
    UNIQUE INDEX `uniq_pra` (`platform_rule_id`, `scope_type`, `scope_id`),
    INDEX `idx_pra_rule` (`platform_rule_id`),
    INDEX `idx_pra_scope` (`scope_type`, `scope_id`),

    -- 外键
    CONSTRAINT `fk_pra_rule` FOREIGN KEY (`platform_rule_id`) REFERENCES `platform_rules`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='平台规则分配表';
