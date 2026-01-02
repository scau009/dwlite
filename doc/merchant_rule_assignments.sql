-- 商户规则分配表
-- 将商户规则分配到具体的渠道配置(MerchantSalesChannel)

CREATE TABLE `merchant_rule_assignments` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_rule_id` VARCHAR(26) NOT NULL COMMENT '商户规则ID',
    `merchant_sales_channel_id` VARCHAR(26) NOT NULL COMMENT '商户渠道配置ID',

    -- 覆盖配置
    `priority_override` INT NULL COMMENT '覆盖规则的默认优先级',
    `config_override` JSON NULL COMMENT '覆盖规则的默认配置',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用此分配',

    -- 审计字段
    `created_at` DATETIME NOT NULL COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL COMMENT '更新时间',

    -- 索引
    UNIQUE INDEX `uniq_mra` (`merchant_rule_id`, `merchant_sales_channel_id`),
    INDEX `idx_mra_rule` (`merchant_rule_id`),
    INDEX `idx_mra_channel` (`merchant_sales_channel_id`),

    -- 外键
    CONSTRAINT `fk_mra_rule` FOREIGN KEY (`merchant_rule_id`) REFERENCES `merchant_rules`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mra_channel` FOREIGN KEY (`merchant_sales_channel_id`) REFERENCES `merchant_sales_channels`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商户规则分配表';
