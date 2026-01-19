-- =============================================================================
-- DWLite Database Schema - Consolidated DDL
-- Generated: 2026-01-15
-- Description: All table definitions in dependency order
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- PHASE 1: Base Reference Tables (No External Dependencies)
-- =============================================================================

-- -----------------------------------------------------------------------------
-- brands
-- -----------------------------------------------------------------------------
CREATE TABLE `brands` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(120) NOT NULL UNIQUE COMMENT 'URL-friendly identifier',
    `logo_url` VARCHAR(500) NULL COMMENT 'Logo URL',
    `description` TEXT NULL,
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT 'Display order, lower first',
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_brand_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Brands';

-- -----------------------------------------------------------------------------
-- categories
-- -----------------------------------------------------------------------------
CREATE TABLE `categories` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `parent_id` VARCHAR(26) NULL COMMENT 'Parent category ID',
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(120) NOT NULL UNIQUE COMMENT 'URL-friendly identifier',
    `description` TEXT NULL,
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT 'Display order, lower first',
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_category_slug` (`slug`),
    INDEX `idx_category_parent` (`parent_id`),
    CONSTRAINT `fk_category_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categories';

-- -----------------------------------------------------------------------------
-- tags
-- -----------------------------------------------------------------------------
CREATE TABLE `tags` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `name` VARCHAR(50) NOT NULL COMMENT 'Tag display name',
    `slug` VARCHAR(60) NOT NULL UNIQUE COMMENT 'URL-friendly identifier',
    `color` VARCHAR(7) NULL COMMENT 'Hex color code, e.g. #FF5733',
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT 'Display order',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Whether tag is active',
    `created_at` DATETIME NOT NULL,
    INDEX `idx_tag_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tags';

-- -----------------------------------------------------------------------------
-- sales_channels
-- -----------------------------------------------------------------------------
CREATE TABLE `sales_channels` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Channel code (taobao, jd, douyin, etc.)',
    `name` VARCHAR(100) NOT NULL,
    `logo_url` VARCHAR(500) NULL,
    `description` TEXT NULL,
    `config` JSON NULL COMMENT 'Channel global config',
    `config_schema` JSON NULL COMMENT 'JSON Schema for merchant config fields',
    `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, maintenance, disabled',
    `sort_order` INT NOT NULL DEFAULT 0,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'CNY' COMMENT 'Currency code (ISO 4217)',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_channel_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sales channels';

-- -----------------------------------------------------------------------------
-- platform_rules
-- -----------------------------------------------------------------------------
CREATE TABLE `platform_rules` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `code` VARCHAR(100) NOT NULL UNIQUE COMMENT '规则编码(全局唯一)',
    `name` VARCHAR(200) NOT NULL COMMENT '规则名称',
    `description` TEXT NULL COMMENT '规则描述',
    `type` VARCHAR(50) NOT NULL COMMENT '规则类型: pricing(加价规则), stock_priority(库存优先级), settlement_fee(结算费率)',
    `category` VARCHAR(50) NOT NULL COMMENT '规则分类: markup(加价), discount(折扣), priority(优先级), fee_rate(费率)',
    `expression` TEXT NOT NULL COMMENT '主表达式(Symfony Expression Language)',
    `condition_expression` TEXT NULL COMMENT '条件表达式(满足条件才执行主表达式)',
    `priority` INT NOT NULL DEFAULT 0 COMMENT '优先级(越小越高)',
    `config` JSON NULL COMMENT '配置参数',
    `is_system` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否系统规则(不可删除)',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
    `created_by` VARCHAR(26) NULL COMMENT '创建人ID',
    `created_at` DATETIME NOT NULL COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL COMMENT '更新时间',
    INDEX `idx_pr_type` (`type`),
    INDEX `idx_pr_category` (`category`),
    INDEX `idx_pr_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='平台规则定义表';

-- -----------------------------------------------------------------------------
-- platform_rule_assignments
-- -----------------------------------------------------------------------------
CREATE TABLE `platform_rule_assignments` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `platform_rule_id` VARCHAR(26) NOT NULL COMMENT '平台规则ID',
    `scope_type` VARCHAR(50) NOT NULL COMMENT '范围类型: merchant(商户), channel_product(渠道商品)',
    `scope_id` VARCHAR(26) NOT NULL COMMENT '对应实体的ID',
    `priority_override` INT NULL COMMENT '覆盖规则的默认优先级',
    `config_override` JSON NULL COMMENT '覆盖规则的默认配置',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用此分配',
    `created_at` DATETIME NOT NULL COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL COMMENT '更新时间',
    UNIQUE INDEX `uniq_pra` (`platform_rule_id`, `scope_type`, `scope_id`),
    INDEX `idx_pra_rule` (`platform_rule_id`),
    INDEX `idx_pra_scope` (`scope_type`, `scope_id`),
    CONSTRAINT `fk_pra_rule` FOREIGN KEY (`platform_rule_id`) REFERENCES `platform_rules`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='平台规则分配表';

-- =============================================================================
-- PHASE 2: Standalone Log Tables (No FK Dependencies)
-- =============================================================================

-- -----------------------------------------------------------------------------
-- processed_messages (Messenger Monitor)
-- -----------------------------------------------------------------------------
CREATE TABLE `processed_messages` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `run_id` INT NOT NULL COMMENT '运行ID',
    `attempt` SMALLINT NOT NULL DEFAULT 1 COMMENT '尝试次数',
    `message_type` VARCHAR(255) NOT NULL COMMENT '消息类型(类名)',
    `description` VARCHAR(255) NULL COMMENT '消息描述',
    `dispatched_at` DATETIME NOT NULL COMMENT '派发时间',
    `received_at` DATETIME NOT NULL COMMENT '接收时间',
    `finished_at` DATETIME NOT NULL COMMENT '完成时间',
    `wait_time` BIGINT NOT NULL COMMENT '等待时间(毫秒)',
    `handle_time` BIGINT NOT NULL COMMENT '处理时间(毫秒)',
    `memory_usage` BIGINT NOT NULL COMMENT '内存使用(字节)',
    `transport` VARCHAR(255) NOT NULL COMMENT '传输通道',
    `tags` VARCHAR(255) NULL COMMENT '标签',
    `failure_type` VARCHAR(255) NULL COMMENT '失败类型(异常类名)',
    `failure_message` TEXT NULL COMMENT '失败消息',
    `results` JSON NULL COMMENT '处理结果',
    PRIMARY KEY (`id`),
    INDEX `idx_run_id` (`run_id`),
    INDEX `idx_message_type` (`message_type`),
    INDEX `idx_transport` (`transport`),
    INDEX `idx_finished_at` (`finished_at`),
    INDEX `idx_failure_type` (`failure_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='消息处理记录';

-- -----------------------------------------------------------------------------
-- product_sync_jobs
-- -----------------------------------------------------------------------------
CREATE TABLE `product_sync_jobs` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `provider` VARCHAR(50) NOT NULL COMMENT 'Provider identifier: kicksdb, goat, etc.',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, running, completed, failed',
    `total_pages` INT NULL COMMENT 'Total pages to sync',
    `processed_pages` INT NOT NULL DEFAULT 0 COMMENT 'Pages processed so far',
    `total_products` INT NULL COMMENT 'Total products to sync',
    `synced_products` INT NOT NULL DEFAULT 0 COMMENT 'Products processed',
    `created_products` INT NOT NULL DEFAULT 0 COMMENT 'New products created',
    `updated_products` INT NOT NULL DEFAULT 0 COMMENT 'Existing products updated',
    `skipped_products` INT NOT NULL DEFAULT 0 COMMENT 'Products skipped (no styleId, etc.)',
    `failed_products` INT NOT NULL DEFAULT 0 COMMENT 'Products failed to sync',
    `error_message` TEXT NULL COMMENT 'Error details if failed',
    `started_at` DATETIME NULL COMMENT 'When sync started (UTC)',
    `completed_at` DATETIME NULL COMMENT 'When sync completed (UTC)',
    `created_at` DATETIME NOT NULL COMMENT 'Record creation time (UTC)',
    INDEX `idx_sync_job_provider` (`provider`),
    INDEX `idx_sync_job_status` (`status`),
    INDEX `idx_sync_job_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product sync jobs';

-- -----------------------------------------------------------------------------
-- listing_operation_logs
-- -----------------------------------------------------------------------------
CREATE TABLE `listing_operation_logs` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID主键',
    `listing_id` VARCHAR(26) NOT NULL COMMENT '上架ID',
    `merchant_id` VARCHAR(26) NOT NULL COMMENT '商户ID',
    `operator_id` VARCHAR(26) NOT NULL COMMENT '操作人ID',
    `operation` VARCHAR(50) NOT NULL COMMENT '操作类型',
    `changes` JSON NULL COMMENT '变更内容 {before: {...}, after: {...}}',
    `created_at` DATETIME NOT NULL COMMENT '操作时间(UTC)',
    INDEX `idx_listing_id` (`listing_id`),
    INDEX `idx_merchant_id` (`merchant_id`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_operation` (`operation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='上架操作日志';

-- -----------------------------------------------------------------------------
-- rule_execution_logs
-- -----------------------------------------------------------------------------
CREATE TABLE `rule_execution_logs` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `rule_type` VARCHAR(20) NOT NULL COMMENT '规则类型: merchant(商户规则), platform(平台规则)',
    `rule_id` VARCHAR(26) NOT NULL COMMENT '规则ID',
    `context_type` VARCHAR(50) NOT NULL COMMENT '执行场景',
    `context_id` VARCHAR(26) NULL COMMENT '相关实体ID',
    `input_data` JSON NOT NULL COMMENT '输入变量',
    `output_value` VARCHAR(255) NULL COMMENT '计算结果',
    `execution_time_ms` INT NOT NULL COMMENT '执行耗时(毫秒)',
    `success` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否成功',
    `error_message` TEXT NULL COMMENT '失败原因',
    `created_at` DATETIME NOT NULL COMMENT '创建时间',
    INDEX `idx_rel_rule` (`rule_type`, `rule_id`),
    INDEX `idx_rel_context` (`context_type`, `context_id`),
    INDEX `idx_rel_created` (`created_at`),
    INDEX `idx_rel_success` (`success`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='规则执行日志表';

-- -----------------------------------------------------------------------------
-- channel_product_sync_logs
-- -----------------------------------------------------------------------------
CREATE TABLE `channel_product_sync_logs` (
    `id` VARCHAR(26) NOT NULL COMMENT 'ULID',
    `channel_product_id` VARCHAR(26) NOT NULL,
    `sales_channel_id` VARCHAR(26) NOT NULL,
    `operation` VARCHAR(50) NOT NULL COMMENT 'aggregate | push_product | update_stock_price | delist',
    `trigger_source` VARCHAR(50) NOT NULL COMMENT 'listing_create | listing_update | inventory_inbound | etc.',
    `trigger_listing_id` VARCHAR(26) NULL,
    `trigger_merchant_id` VARCHAR(26) NULL,
    `trigger_inventory_id` VARCHAR(26) NULL,
    `before_data` JSON NULL COMMENT 'State before sync',
    `after_data` JSON NULL COMMENT 'State after sync',
    `status` VARCHAR(20) NOT NULL COMMENT 'pending | processing | success | failed | skipped',
    `error_code` VARCHAR(50) NULL,
    `error_message` TEXT NULL,
    `external_response` JSON NULL,
    `started_at` DATETIME NOT NULL,
    `completed_at` DATETIME NULL,
    `duration_ms` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_channel_product` (`channel_product_id`),
    KEY `idx_sales_channel` (`sales_channel_id`),
    KEY `idx_status` (`status`),
    KEY `idx_trigger_merchant` (`trigger_merchant_id`),
    KEY `idx_trigger_listing` (`trigger_listing_id`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_operation_status` (`operation`, `status`),
    KEY `idx_started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Channel product synchronization logs';

-- -----------------------------------------------------------------------------
-- order_sync_logs
-- -----------------------------------------------------------------------------
CREATE TABLE `order_sync_logs` (
    `id` VARCHAR(26) NOT NULL COMMENT 'ULID',
    `order_id` VARCHAR(26) NULL COMMENT '关联订单ID',
    `sales_channel_id` VARCHAR(26) NOT NULL,
    `external_order_id` VARCHAR(100) NULL,
    `direction` VARCHAR(10) NOT NULL COMMENT 'pull|push',
    `operation` VARCHAR(50) NOT NULL COMMENT 'pull_order|confirm_order|ship_order|cancel_order',
    `status` VARCHAR(20) NOT NULL COMMENT 'pending|processing|success|failed|skipped',
    `request_data` JSON NULL,
    `response_data` JSON NULL,
    `error_code` VARCHAR(50) NULL,
    `error_message` TEXT NULL,
    `retry_count` INT NOT NULL DEFAULT 0,
    `started_at` DATETIME NOT NULL,
    `completed_at` DATETIME NULL,
    `duration_ms` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_order` (`order_id`),
    KEY `idx_sales_channel` (`sales_channel_id`),
    KEY `idx_external_order` (`external_order_id`),
    KEY `idx_direction_status` (`direction`, `status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单同步日志';

-- =============================================================================
-- PHASE 3: Core Entity Tables (Circular Dependency Handled by FK_CHECKS=0)
-- =============================================================================

-- -----------------------------------------------------------------------------
-- warehouses
-- -----------------------------------------------------------------------------
CREATE TABLE `warehouses` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique warehouse code',
    `name` VARCHAR(100) NOT NULL COMMENT 'Warehouse name',
    `short_name` VARCHAR(100) NULL COMMENT 'Short name',
    `type` VARCHAR(20) NOT NULL DEFAULT 'third_party' COMMENT 'self, third_party, bonded, overseas',
    `category` VARCHAR(20) NOT NULL DEFAULT 'platform' COMMENT 'platform, merchant',
    `merchant_id` VARCHAR(26) NULL COMMENT 'Merchant ID for merchant-owned warehouses',
    `description` TEXT NULL,
    `country_code` VARCHAR(2) NOT NULL DEFAULT 'CN' COMMENT 'ISO 3166-1 alpha-2',
    `timezone` VARCHAR(50) NULL,
    `province` VARCHAR(50) NULL,
    `city` VARCHAR(50) NULL,
    `district` VARCHAR(50) NULL,
    `address` VARCHAR(255) NULL,
    `postal_code` VARCHAR(20) NULL,
    `longitude` DECIMAL(10, 7) NULL,
    `latitude` DECIMAL(10, 7) NULL,
    `contact_name` VARCHAR(50) NOT NULL DEFAULT '',
    `contact_phone` VARCHAR(20) NOT NULL DEFAULT '',
    `contact_email` VARCHAR(100) NULL,
    `internal_notes` TEXT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, maintenance, disabled',
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_warehouse_code` (`code`),
    INDEX `idx_warehouse_status` (`status`),
    INDEX `idx_warehouse_type` (`type`),
    INDEX `idx_warehouse_category` (`category`),
    INDEX `idx_warehouse_merchant` (`merchant_id`),
    INDEX `idx_warehouse_country` (`country_code`),
    CONSTRAINT `fk_warehouse_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Warehouses';

-- -----------------------------------------------------------------------------
-- users
-- -----------------------------------------------------------------------------
CREATE TABLE `users` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `email` VARCHAR(180) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `roles` JSON NOT NULL COMMENT 'User roles array',
    `account_type` VARCHAR(50) NOT NULL DEFAULT 'merchant' COMMENT 'admin, merchant, warehouse',
    `warehouse_id` VARCHAR(26) NULL COMMENT 'Associated warehouse for warehouse account type',
    `is_verified` BOOLEAN NOT NULL DEFAULT FALSE,
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_user_email` (`email`),
    INDEX `idx_user_active` (`is_active`),
    INDEX `idx_user_warehouse` (`warehouse_id`),
    CONSTRAINT `fk_user_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User accounts';

-- -----------------------------------------------------------------------------
-- merchants
-- -----------------------------------------------------------------------------
CREATE TABLE `merchants` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `user_id` VARCHAR(26) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `logo` VARCHAR(255) NULL COMMENT 'Merchant logo URL',
    `description` TEXT NULL,
    `contact_name` VARCHAR(50) NOT NULL,
    `contact_phone` VARCHAR(20) NOT NULL,
    `province` VARCHAR(50) NULL,
    `city` VARCHAR(50) NULL,
    `district` VARCHAR(50) NULL,
    `address` VARCHAR(255) NULL,
    `business_license` VARCHAR(100) NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, suspended, inactive',
    `approved_at` DATETIME NULL,
    `rejected_reason` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_merchant_status` (`status`),
    CONSTRAINT `fk_merchant_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Merchants';

-- =============================================================================
-- PHASE 4: User/Merchant Related Tables
-- =============================================================================

-- -----------------------------------------------------------------------------
-- wallets
-- -----------------------------------------------------------------------------
CREATE TABLE `wallets` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_id` VARCHAR(26) NOT NULL,
    `type` VARCHAR(20) NOT NULL DEFAULT 'deposit' COMMENT 'deposit, balance',
    `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `frozen_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, frozen',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE KEY `uk_merchant_type` (`merchant_id`, `type`),
    INDEX `idx_wallet_merchant` (`merchant_id`),
    CONSTRAINT `fk_wallet_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Merchant wallets';

-- -----------------------------------------------------------------------------
-- wallet_transactions
-- -----------------------------------------------------------------------------
CREATE TABLE `wallet_transactions` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `transaction_no` VARCHAR(20) NOT NULL UNIQUE COMMENT 'WT2024121900001',
    `wallet_id` VARCHAR(26) NOT NULL,
    `type` VARCHAR(20) NOT NULL COMMENT 'credit, debit, freeze, unfreeze',
    `amount` DECIMAL(12,2) NOT NULL,
    `balance_before` DECIMAL(12,2) NOT NULL,
    `balance_after` DECIMAL(12,2) NOT NULL,
    `biz_type` VARCHAR(50) NOT NULL COMMENT 'deposit_charge, order_income, withdraw, etc.',
    `biz_id` VARCHAR(26) NULL,
    `remark` VARCHAR(255) NULL,
    `operator_id` VARCHAR(26) NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_wallet_created` (`wallet_id`, `created_at`),
    INDEX `idx_biz` (`biz_type`, `biz_id`),
    INDEX `idx_transaction_no` (`transaction_no`),
    CONSTRAINT `fk_wt_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Wallet transactions';

-- -----------------------------------------------------------------------------
-- refresh_tokens
-- -----------------------------------------------------------------------------
CREATE TABLE `refresh_tokens` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `token` VARCHAR(128) NOT NULL UNIQUE,
    `user_id` VARCHAR(26) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL,
    `revoked` BOOLEAN NOT NULL DEFAULT FALSE,
    INDEX `idx_refresh_token` (`token`),
    INDEX `idx_refresh_token_expires` (`expires_at`),
    INDEX `idx_rt_user` (`user_id`),
    INDEX `idx_rt_revoked` (`revoked`),
    CONSTRAINT `fk_rt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Refresh tokens';

-- -----------------------------------------------------------------------------
-- email_verification_tokens
-- -----------------------------------------------------------------------------
CREATE TABLE `email_verification_tokens` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `user_id` VARCHAR(26) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_evt_token` (`token`),
    INDEX `idx_evt_user` (`user_id`),
    INDEX `idx_evt_expires` (`expires_at`),
    CONSTRAINT `fk_evt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Email verification tokens';

-- -----------------------------------------------------------------------------
-- password_reset_tokens
-- -----------------------------------------------------------------------------
CREATE TABLE `password_reset_tokens` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `user_id` VARCHAR(26) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_prt_token` (`token`),
    INDEX `idx_prt_user` (`user_id`),
    INDEX `idx_prt_expires` (`expires_at`),
    CONSTRAINT `fk_prt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Password reset tokens';

-- -----------------------------------------------------------------------------
-- merchant_bank_accounts
-- -----------------------------------------------------------------------------
CREATE TABLE `merchant_bank_accounts` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_id` VARCHAR(26) NOT NULL,
    `bank_name` VARCHAR(100) NOT NULL,
    `bank_code` VARCHAR(20) NULL COMMENT 'SWIFT etc.',
    `branch_name` VARCHAR(200) NULL,
    `account_number` VARCHAR(50) NOT NULL,
    `account_holder` VARCHAR(100) NOT NULL,
    `account_type` VARCHAR(20) NOT NULL DEFAULT 'corporate' COMMENT 'corporate, personal',
    `currency` VARCHAR(3) NOT NULL DEFAULT 'CNY',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'active, pending, disabled',
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `verified_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_mba_merchant` (`merchant_id`),
    INDEX `idx_mba_default` (`merchant_id`, `is_default`),
    CONSTRAINT `fk_mba_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Merchant bank accounts';

-- =============================================================================
-- PHASE 5: Product Chain
-- =============================================================================

-- -----------------------------------------------------------------------------
-- products
-- -----------------------------------------------------------------------------
CREATE TABLE `products` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `brand_id` VARCHAR(26) NULL,
    `category_id` VARCHAR(26) NULL,
    `name` VARCHAR(200) NOT NULL,
    `slug` VARCHAR(220) NOT NULL UNIQUE,
    `style_number` VARCHAR(100) NOT NULL COMMENT '款号',
    `season` VARCHAR(20) NOT NULL COMMENT '季节: 2024SS, 2024AW, 2024FW',
    `color` VARCHAR(50) NULL,
    `description` TEXT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'draft, active, inactive',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_product_slug` (`slug`),
    INDEX `idx_product_style_number` (`style_number`),
    INDEX `idx_product_season` (`season`),
    INDEX `idx_product_brand` (`brand_id`),
    INDEX `idx_product_category` (`category_id`),
    INDEX `idx_product_active` (`is_active`),
    INDEX `idx_product_status` (`status`),
    INDEX `idx_product_name` (`name`),
    CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_product_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Products (SPU)';

-- -----------------------------------------------------------------------------
-- product_tags
-- -----------------------------------------------------------------------------
CREATE TABLE `product_tags` (
    `product_id` VARCHAR(26) NOT NULL,
    `tag_id` VARCHAR(26) NOT NULL,
    PRIMARY KEY (`product_id`, `tag_id`),
    INDEX `idx_product_tags_product` (`product_id`),
    INDEX `idx_product_tags_tag` (`tag_id`),
    CONSTRAINT `fk_product_tags_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_product_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product-Tag associations';

-- -----------------------------------------------------------------------------
-- product_images
-- -----------------------------------------------------------------------------
CREATE TABLE `product_images` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `product_id` VARCHAR(26) NOT NULL,
    `cos_key` VARCHAR(255) NOT NULL COMMENT 'COS object key',
    `url` VARCHAR(500) NOT NULL COMMENT 'Full CDN URL',
    `thumbnail_url` VARCHAR(500) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_primary` BOOLEAN NOT NULL DEFAULT FALSE,
    `file_size` INT NULL COMMENT 'Bytes',
    `width` INT NULL,
    `height` INT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_image_product` (`product_id`),
    INDEX `idx_image_cos_key` (`cos_key`),
    CONSTRAINT `fk_image_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product images';

-- -----------------------------------------------------------------------------
-- product_skus
-- -----------------------------------------------------------------------------
CREATE TABLE `product_skus` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `product_id` VARCHAR(26) NOT NULL,
    `size_unit` ENUM('EU', 'US', 'UK', 'CM') NULL,
    `size_value` VARCHAR(20) NULL,
    `spec_info` JSON NULL,
    `price` DECIMAL(10,2) NOT NULL COMMENT 'Reference price',
    `original_price` DECIMAL(10,2) NULL COMMENT 'Release price',
    `currency` VARCHAR(3) NOT NULL DEFAULT 'USD',
    `barcode` VARCHAR(50) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_sku_product` (`product_id`),
    INDEX `idx_sku_active` (`is_active`),
    CONSTRAINT `fk_sku_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product SKUs';

-- -----------------------------------------------------------------------------
-- product_external_mappings
-- -----------------------------------------------------------------------------
CREATE TABLE `product_external_mappings` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `product_id` VARCHAR(26) NOT NULL,
    `provider` VARCHAR(50) NOT NULL COMMENT 'kicksdb, goat, stockx, etc.',
    `external_id` VARCHAR(100) NOT NULL,
    `external_style_id` VARCHAR(100) NULL,
    `external_url` VARCHAR(500) NULL,
    `external_data` JSON NULL,
    `last_synced_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE KEY `uk_product_provider` (`product_id`, `provider`),
    UNIQUE KEY `uk_provider_external` (`provider`, `external_id`),
    INDEX `idx_mapping_provider` (`provider`),
    INDEX `idx_mapping_style_id` (`provider`, `external_style_id`),
    CONSTRAINT `fk_mapping_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product external source mappings';

-- =============================================================================
-- PHASE 6: Merchant-Channel & Warehouse-Channel
-- =============================================================================

-- -----------------------------------------------------------------------------
-- merchant_sales_channels
-- -----------------------------------------------------------------------------
CREATE TABLE `merchant_sales_channels` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_id` VARCHAR(26) NOT NULL,
    `sales_channel_id` VARCHAR(26) NOT NULL,
    `requested_fulfillment_types` JSON NOT NULL COMMENT '["consignment", "self_fulfillment"]',
    `approved_fulfillment_types` JSON NULL,
    `config` JSON NULL COMMENT 'Shop ID, API keys, etc.',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, active, suspended, disabled, rejected',
    `approved_at` DATETIME NULL,
    `approved_by` VARCHAR(26) NULL,
    `remark` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE INDEX `uk_merchant_channel` (`merchant_id`, `sales_channel_id`),
    INDEX `idx_msc_merchant` (`merchant_id`),
    INDEX `idx_msc_channel` (`sales_channel_id`),
    CONSTRAINT `fk_msc_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_msc_channel` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Merchant-SalesChannel connections';

-- -----------------------------------------------------------------------------
-- sales_channel_warehouses
-- -----------------------------------------------------------------------------
CREATE TABLE `sales_channel_warehouses` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `sales_channel_id` VARCHAR(26) NOT NULL,
    `warehouse_id` VARCHAR(26) NOT NULL,
    `priority` INT NOT NULL DEFAULT 0 COMMENT 'Lower = higher priority',
    `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, disabled',
    `remark` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE INDEX `uk_channel_warehouse` (`sales_channel_id`, `warehouse_id`),
    INDEX `idx_scw_channel` (`sales_channel_id`),
    INDEX `idx_scw_warehouse` (`warehouse_id`),
    INDEX `idx_scw_priority` (`priority`),
    INDEX `idx_scw_status` (`status`),
    CONSTRAINT `fk_scw_channel` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channels` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_scw_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sales channel fulfillment warehouses';

-- =============================================================================
-- PHASE 7: Inventory System
-- =============================================================================

-- -----------------------------------------------------------------------------
-- merchant_inventories
-- -----------------------------------------------------------------------------
CREATE TABLE `merchant_inventories` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_id` VARCHAR(26) NOT NULL,
    `warehouse_id` VARCHAR(26) NOT NULL,
    `product_sku_id` VARCHAR(26) NOT NULL,
    `quantity_in_transit` INT NOT NULL DEFAULT 0,
    `quantity_available` INT NOT NULL DEFAULT 0,
    `quantity_reserved` INT NOT NULL DEFAULT 0,
    `quantity_damaged` INT NOT NULL DEFAULT 0,
    `quantity_allocated` INT NOT NULL DEFAULT 0 COMMENT '渠道独占分配的库存',
    `average_cost` DECIMAL(10,2) NULL,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'CNY',
    `safety_stock` INT NULL,
    `last_inbound_at` DATETIME NULL,
    `last_outbound_at` DATETIME NULL,
    `last_synced_at` DATETIME NULL,
    `external_sku_id` VARCHAR(100) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_inventory_merchant` (`merchant_id`),
    INDEX `idx_inventory_warehouse` (`warehouse_id`),
    INDEX `idx_inventory_sku` (`product_sku_id`),
    UNIQUE INDEX `uniq_merchant_warehouse_sku` (`merchant_id`, `warehouse_id`, `product_sku_id`),
    CONSTRAINT `fk_mi_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mi_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mi_sku` FOREIGN KEY (`product_sku_id`) REFERENCES `product_skus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商户库存';

-- -----------------------------------------------------------------------------
-- inventory_listings
-- -----------------------------------------------------------------------------
CREATE TABLE `inventory_listings` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_inventory_id` VARCHAR(26) NOT NULL,
    `merchant_sales_channel_id` VARCHAR(26) NOT NULL,
    `allocation_mode` VARCHAR(20) NOT NULL DEFAULT 'shared' COMMENT 'shared, dedicated',
    `allocated_quantity` INT NULL,
    `sold_quantity` INT NOT NULL DEFAULT 0,
    `fulfillment_type` VARCHAR(20) NOT NULL DEFAULT 'consignment' COMMENT 'consignment, self_fulfillment',
    `pricing_model` VARCHAR(20) NOT NULL DEFAULT 'self_pricing' COMMENT 'self_pricing, platform_managed',
    `price` DECIMAL(10,2) NOT NULL,
    `compare_at_price` DECIMAL(10,2) NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'draft, active, paused, sold_out',
    `remark` TEXT NULL,
    `price_rule_expression` TEXT NULL,
    `stock_rule_expression` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE INDEX `uniq_inventory_channel` (`merchant_inventory_id`, `merchant_sales_channel_id`),
    INDEX `idx_listing_inventory` (`merchant_inventory_id`),
    INDEX `idx_listing_channel` (`merchant_sales_channel_id`),
    INDEX `idx_listing_status` (`status`),
    INDEX `idx_listing_fulfillment` (`fulfillment_type`),
    INDEX `idx_listing_pricing` (`pricing_model`),
    CONSTRAINT `fk_listing_inventory` FOREIGN KEY (`merchant_inventory_id`) REFERENCES `merchant_inventories` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_listing_channel` FOREIGN KEY (`merchant_sales_channel_id`) REFERENCES `merchant_sales_channels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Merchant inventory listings';

-- -----------------------------------------------------------------------------
-- inventory_transactions
-- -----------------------------------------------------------------------------
CREATE TABLE `inventory_transactions` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_inventory_id` VARCHAR(26) NOT NULL,
    `type` VARCHAR(30) NOT NULL COMMENT '变动类型',
    `quantity` INT NOT NULL,
    `stock_type` VARCHAR(20) NOT NULL COMMENT 'in_transit, available, reserved, damaged',
    `balance_before` INT NOT NULL,
    `balance_after` INT NOT NULL,
    `reference_type` VARCHAR(30) NULL,
    `reference_id` VARCHAR(26) NULL,
    `reference_no` VARCHAR(50) NULL,
    `unit_cost` DECIMAL(10,2) NULL,
    `operator_id` VARCHAR(26) NULL,
    `operator_name` VARCHAR(50) NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_inv_trans_inventory` (`merchant_inventory_id`),
    INDEX `idx_inv_trans_type` (`type`),
    INDEX `idx_inv_trans_reference` (`reference_type`, `reference_id`),
    INDEX `idx_inv_trans_created` (`created_at`),
    CONSTRAINT `fk_inv_trans_inventory` FOREIGN KEY (`merchant_inventory_id`) REFERENCES `merchant_inventories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='库存流水';

-- =============================================================================
-- PHASE 8: Merchant Rules
-- =============================================================================

-- -----------------------------------------------------------------------------
-- merchant_rules
-- -----------------------------------------------------------------------------
CREATE TABLE `merchant_rules` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_id` VARCHAR(26) NOT NULL,
    `code` VARCHAR(100) NOT NULL COMMENT '规则编码(商户内唯一)',
    `name` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `type` VARCHAR(50) NOT NULL COMMENT 'pricing, stock_allocation',
    `category` VARCHAR(50) NOT NULL COMMENT 'markup, discount, ratio, limit',
    `expression` TEXT NOT NULL,
    `condition_expression` TEXT NULL,
    `priority` INT NOT NULL DEFAULT 0,
    `config` JSON NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE INDEX `uniq_merchant_rule_code` (`merchant_id`, `code`),
    INDEX `idx_mr_merchant` (`merchant_id`),
    INDEX `idx_mr_type` (`type`),
    INDEX `idx_mr_active` (`is_active`),
    CONSTRAINT `fk_mr_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商户规则定义表';

-- -----------------------------------------------------------------------------
-- merchant_rule_assignments
-- -----------------------------------------------------------------------------
CREATE TABLE `merchant_rule_assignments` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_rule_id` VARCHAR(26) NOT NULL,
    `merchant_sales_channel_id` VARCHAR(26) NOT NULL,
    `priority_override` INT NULL,
    `config_override` JSON NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE INDEX `uniq_mra` (`merchant_rule_id`, `merchant_sales_channel_id`),
    INDEX `idx_mra_rule` (`merchant_rule_id`),
    INDEX `idx_mra_channel` (`merchant_sales_channel_id`),
    CONSTRAINT `fk_mra_rule` FOREIGN KEY (`merchant_rule_id`) REFERENCES `merchant_rules`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mra_channel` FOREIGN KEY (`merchant_sales_channel_id`) REFERENCES `merchant_sales_channels`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商户规则分配表';

-- =============================================================================
-- PHASE 9: Inbound Orders
-- =============================================================================

-- -----------------------------------------------------------------------------
-- inbound_orders
-- -----------------------------------------------------------------------------
CREATE TABLE `inbound_orders` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `order_no` VARCHAR(32) NOT NULL UNIQUE COMMENT 'IB...',
    `merchant_id` VARCHAR(26) NOT NULL,
    `warehouse_id` VARCHAR(26) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'draft, pending, shipped, arrived, receiving, completed, partial_completed, cancelled',
    `total_sku_count` INT NOT NULL DEFAULT 0,
    `total_quantity` INT NOT NULL DEFAULT 0,
    `received_quantity` INT NOT NULL DEFAULT 0,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'USD' COMMENT 'Cost currency (CNY, USD, EUR, HKD, JPY)',
    `expected_arrival_date` DATE NULL,
    `submitted_at` DATETIME NULL,
    `shipped_at` DATETIME NULL,
    `arrived_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `cancelled_at` DATETIME NULL,
    `merchant_notes` TEXT NULL,
    `warehouse_notes` TEXT NULL,
    `cancel_reason` VARCHAR(100) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_inbound_order_no` (`order_no`),
    INDEX `idx_inbound_merchant` (`merchant_id`),
    INDEX `idx_inbound_warehouse` (`warehouse_id`),
    INDEX `idx_inbound_status` (`status`),
    INDEX `idx_inbound_created` (`created_at`),
    CONSTRAINT `fk_inbound_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`),
    CONSTRAINT `fk_inbound_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inbound orders';

-- -----------------------------------------------------------------------------
-- inbound_order_items
-- -----------------------------------------------------------------------------
CREATE TABLE `inbound_order_items` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `inbound_order_id` VARCHAR(26) NOT NULL,
    `product_sku_id` VARCHAR(26) NULL,
    `sku_name` VARCHAR(255) NULL,
    `style_number` VARCHAR(255) NULL,
    `color_name` VARCHAR(255) NULL,
    `product_name` VARCHAR(255) NULL,
    `product_image` VARCHAR(500) NULL,
    `expected_quantity` INT NOT NULL,
    `received_quantity` INT NOT NULL DEFAULT 0,
    `damaged_quantity` INT NOT NULL DEFAULT 0,
    `unit_cost` DECIMAL(10,2) NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, received, partial, over, missing',
    `warehouse_remark` TEXT NULL,
    `received_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_inbound_item_order` (`inbound_order_id`),
    INDEX `idx_inbound_item_sku` (`product_sku_id`),
    UNIQUE INDEX `uniq_inbound_order_sku` (`inbound_order_id`, `product_sku_id`),
    CONSTRAINT `fk_inbound_item_order` FOREIGN KEY (`inbound_order_id`) REFERENCES `inbound_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_inbound_item_sku` FOREIGN KEY (`product_sku_id`) REFERENCES `product_skus` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inbound order items';

-- -----------------------------------------------------------------------------
-- inbound_shipments
-- -----------------------------------------------------------------------------
CREATE TABLE `inbound_shipments` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `inbound_order_id` VARCHAR(26) NOT NULL UNIQUE,
    `carrier_code` VARCHAR(20) NOT NULL,
    `carrier_name` VARCHAR(50) NULL,
    `tracking_number` VARCHAR(50) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, picked, in_transit, delivered, exception',
    `sender_name` VARCHAR(50) NOT NULL,
    `sender_phone` VARCHAR(20) NOT NULL,
    `sender_address` VARCHAR(255) NOT NULL,
    `sender_province` VARCHAR(50) NULL,
    `sender_city` VARCHAR(50) NULL,
    `box_count` INT NOT NULL DEFAULT 1,
    `total_weight` DECIMAL(10,2) NULL,
    `total_volume` DECIMAL(10,2) NULL,
    `shipped_at` DATETIME NOT NULL,
    `estimated_arrival_date` DATE NULL,
    `delivered_at` DATETIME NULL,
    `tracking_history` JSON NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_inbound_shipment_order` (`inbound_order_id`),
    INDEX `idx_inbound_shipment_tracking` (`tracking_number`),
    INDEX `idx_inbound_shipment_carrier` (`carrier_code`),
    CONSTRAINT `fk_inbound_shipment_order` FOREIGN KEY (`inbound_order_id`) REFERENCES `inbound_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inbound shipments';

-- -----------------------------------------------------------------------------
-- inbound_exceptions
-- -----------------------------------------------------------------------------
CREATE TABLE `inbound_exceptions` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `exception_no` VARCHAR(32) NOT NULL UNIQUE COMMENT 'EX...',
    `inbound_order_id` VARCHAR(26) NOT NULL,
    `merchant_id` VARCHAR(26) NOT NULL,
    `warehouse_id` VARCHAR(26) NOT NULL,
    `type` VARCHAR(30) NOT NULL COMMENT 'quantity_short, quantity_over, damaged, etc.',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, processing, resolved, closed',
    `total_quantity` INT NOT NULL DEFAULT 0,
    `description` TEXT NOT NULL,
    `evidence_images` JSON NULL,
    `resolution` VARCHAR(30) NULL COMMENT 'accept, reject, claim, recount, partial_accept',
    `resolution_notes` TEXT NULL,
    `claim_amount` DECIMAL(10,2) NULL,
    `resolved_at` DATETIME NULL,
    `communication_log` JSON NULL,
    `reported_by` VARCHAR(26) NULL,
    `resolved_by` VARCHAR(26) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_inbound_exc_no` (`exception_no`),
    INDEX `idx_inbound_exc_order` (`inbound_order_id`),
    INDEX `idx_inbound_exc_merchant` (`merchant_id`),
    INDEX `idx_inbound_exc_status` (`status`),
    INDEX `idx_inbound_exc_created` (`created_at`),
    CONSTRAINT `fk_inbound_exc_order` FOREIGN KEY (`inbound_order_id`) REFERENCES `inbound_orders` (`id`),
    CONSTRAINT `fk_inbound_exc_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`),
    CONSTRAINT `fk_inbound_exc_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inbound exceptions';

-- -----------------------------------------------------------------------------
-- inbound_exception_items
-- -----------------------------------------------------------------------------
CREATE TABLE `inbound_exception_items` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `inbound_exception_id` VARCHAR(26) NOT NULL,
    `inbound_order_item_id` VARCHAR(26) NULL,
    `sku_name` VARCHAR(255) NULL,
    `color_name` VARCHAR(255) NULL,
    `product_name` VARCHAR(255) NULL,
    `product_image` VARCHAR(500) NULL,
    `quantity` INT NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_exception_item_exception` (`inbound_exception_id`),
    INDEX `idx_exception_item_order_item` (`inbound_order_item_id`),
    CONSTRAINT `fk_exception_item_exception` FOREIGN KEY (`inbound_exception_id`) REFERENCES `inbound_exceptions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_exception_item_order_item` FOREIGN KEY (`inbound_order_item_id`) REFERENCES `inbound_order_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inbound exception items';

-- =============================================================================
-- PHASE 10: Channel Products
-- =============================================================================

-- -----------------------------------------------------------------------------
-- channel_products
-- -----------------------------------------------------------------------------
CREATE TABLE `channel_products` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `sales_channel_id` VARCHAR(26) NOT NULL,
    `product_sku_id` VARCHAR(26) NOT NULL,
    `platform_price` DECIMAL(10,2) NOT NULL,
    `platform_compare_at_price` DECIMAL(10,2) NULL,
    `stock_mode` VARCHAR(20) NOT NULL DEFAULT 'aggregate' COMMENT 'aggregate, lowest, fixed',
    `stock_quantity` INT NOT NULL DEFAULT 0,
    `safety_buffer` INT NOT NULL DEFAULT 0,
    `fixed_stock` INT NULL,
    `external_id` VARCHAR(100) NULL,
    `external_url` VARCHAR(500) NULL,
    `sync_status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, syncing, synced, failed',
    `last_synced_at` DATETIME NULL,
    `sync_error` TEXT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'draft, pending, active, paused, rejected',
    `total_sold_quantity` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE INDEX `uniq_channel_sku` (`sales_channel_id`, `product_sku_id`),
    INDEX `idx_cp_channel` (`sales_channel_id`),
    INDEX `idx_cp_sku` (`product_sku_id`),
    INDEX `idx_cp_status` (`status`),
    INDEX `idx_cp_external` (`external_id`),
    CONSTRAINT `fk_cp_channel` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channels` (`id`),
    CONSTRAINT `fk_cp_sku` FOREIGN KEY (`product_sku_id`) REFERENCES `product_skus` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Platform channel products';

-- -----------------------------------------------------------------------------
-- channel_product_sources
-- -----------------------------------------------------------------------------
CREATE TABLE `channel_product_sources` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `channel_product_id` VARCHAR(26) NOT NULL,
    `inventory_listing_id` VARCHAR(26) NOT NULL,
    `priority` INT NOT NULL DEFAULT 0,
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `sold_quantity` INT NOT NULL DEFAULT 0,
    `remark` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE INDEX `uniq_product_listing` (`channel_product_id`, `inventory_listing_id`),
    INDEX `idx_cps_product` (`channel_product_id`),
    INDEX `idx_cps_listing` (`inventory_listing_id`),
    CONSTRAINT `fk_cps_product` FOREIGN KEY (`channel_product_id`) REFERENCES `channel_products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cps_listing` FOREIGN KEY (`inventory_listing_id`) REFERENCES `inventory_listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Channel product sources';

-- =============================================================================
-- PHASE 11: Orders
-- =============================================================================

-- -----------------------------------------------------------------------------
-- orders
-- -----------------------------------------------------------------------------
CREATE TABLE `orders` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `order_no` VARCHAR(30) NOT NULL UNIQUE,
    `sales_channel_id` VARCHAR(26) NOT NULL,
    `external_order_id` VARCHAR(100) NOT NULL,
    `external_order_no` VARCHAR(100) NULL,
    `receiver_name` VARCHAR(50) NOT NULL,
    `receiver_phone` VARCHAR(30) NOT NULL,
    `receiver_province` VARCHAR(50) NULL,
    `receiver_city` VARCHAR(50) NULL,
    `receiver_district` VARCHAR(50) NULL,
    `receiver_address` VARCHAR(500) NOT NULL,
    `receiver_postal_code` VARCHAR(20) NULL,
    `total_amount` DECIMAL(12,2) NOT NULL,
    `product_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `shipping_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'CNY',
    `status` VARCHAR(30) NOT NULL DEFAULT 'pending' COMMENT 'pending, allocating, allocated, etc.',
    `payment_status` VARCHAR(30) NOT NULL DEFAULT 'pending' COMMENT 'pending, paid, refunded, partial_refunded',
    `placed_at` DATETIME NOT NULL,
    `paid_at` DATETIME NULL,
    `allocated_at` DATETIME NULL,
    `shipped_at` DATETIME NULL,
    `delivered_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `cancelled_at` DATETIME NULL,
    `buyer_remark` TEXT NULL,
    `seller_remark` TEXT NULL,
    `allocation_fail_reason` TEXT NULL,
    `synced_at` DATETIME NOT NULL,
    `external_data` JSON NULL,
    `label` VARCHAR(500) NULL COMMENT 'Shipping label URL',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_order_channel` (`sales_channel_id`),
    INDEX `idx_order_status` (`status`),
    INDEX `idx_order_external` (`external_order_id`),
    INDEX `idx_order_placed` (`placed_at`),
    CONSTRAINT `fk_order_channel` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Orders';

-- -----------------------------------------------------------------------------
-- order_items
-- -----------------------------------------------------------------------------
CREATE TABLE `order_items` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `order_id` VARCHAR(26) NOT NULL,
    `product_sku_id` VARCHAR(26) NULL,
    `sku_code` VARCHAR(50) NULL,
    `color_code` VARCHAR(20) NULL,
    `size_value` VARCHAR(20) NULL,
    `spec_info` JSON NULL,
    `product_name` VARCHAR(255) NULL,
    `product_image` VARCHAR(500) NULL,
    `channel_product_id` VARCHAR(26) NULL,
    `external_product_id` VARCHAR(100) NULL,
    `external_product_name` VARCHAR(255) NULL,
    `external_product_image` VARCHAR(500) NULL,
    `quantity` INT NOT NULL,
    `allocated_quantity` INT NOT NULL DEFAULT 0,
    `shipped_quantity` INT NOT NULL DEFAULT 0,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `total_price` DECIMAL(12,2) NOT NULL,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payable_amount` DECIMAL(12,2) NOT NULL,
    `allocation_status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, partial, full, failed',
    `remark` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_order_item_order` (`order_id`),
    INDEX `idx_order_item_sku` (`product_sku_id`),
    INDEX `idx_order_item_channel_product` (`channel_product_id`),
    CONSTRAINT `fk_order_item_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_item_sku` FOREIGN KEY (`product_sku_id`) REFERENCES `product_skus` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_order_item_channel_product` FOREIGN KEY (`channel_product_id`) REFERENCES `channel_products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Order items';

-- -----------------------------------------------------------------------------
-- order_exceptions
-- -----------------------------------------------------------------------------
CREATE TABLE `order_exceptions` (
    `id` VARCHAR(26) NOT NULL COMMENT 'ULID',
    `exception_no` VARCHAR(32) NOT NULL COMMENT 'OE...',
    `order_id` VARCHAR(26) NOT NULL,
    `type` VARCHAR(30) NOT NULL COMMENT 'inventory_insufficient|price_below_platform|product_not_matched|other',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending|resolved|closed',
    `description` TEXT NOT NULL,
    `details` JSON NULL,
    `resolution` VARCHAR(30) NULL COMMENT 'confirm|cancel|adjusted',
    `resolution_notes` TEXT NULL,
    `resolved_by` VARCHAR(26) NULL,
    `resolved_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_exception_no` (`exception_no`),
    KEY `idx_order_exc_order` (`order_id`),
    KEY `idx_order_exc_status` (`status`),
    KEY `idx_order_exc_type` (`type`),
    KEY `idx_order_exc_created` (`created_at`),
    CONSTRAINT `fk_order_exception_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单异常工单';

-- -----------------------------------------------------------------------------
-- order_sync_states
-- -----------------------------------------------------------------------------
CREATE TABLE `order_sync_states` (
    `id` VARCHAR(26) NOT NULL COMMENT 'ULID',
    `order_id` VARCHAR(26) NOT NULL,
    `is_pulled` TINYINT(1) NOT NULL DEFAULT 0,
    `is_confirmed` TINYINT(1) NOT NULL DEFAULT 0,
    `is_cancelled` TINYINT(1) NOT NULL DEFAULT 0,
    `shipped_sync_count` INT NOT NULL DEFAULT 0,
    `last_shipped_sync_at` DATETIME NULL,
    `pending_operation` VARCHAR(20) NULL COMMENT 'confirm|ship|cancel',
    `retry_count` INT NOT NULL DEFAULT 0,
    `last_attempt_at` DATETIME NULL,
    `last_error_code` VARCHAR(50) NULL,
    `last_error_message` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order` (`order_id`),
    KEY `idx_pending_op` (`pending_operation`, `retry_count`, `last_attempt_at`),
    CONSTRAINT `fk_order_sync_state_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单同步状态';

-- =============================================================================
-- PHASE 12: Fulfillments
-- =============================================================================

-- -----------------------------------------------------------------------------
-- fulfillments
-- -----------------------------------------------------------------------------
CREATE TABLE `fulfillments` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `fulfillment_no` VARCHAR(30) NOT NULL UNIQUE,
    `order_id` VARCHAR(26) NOT NULL,
    `fulfillment_type` VARCHAR(30) NOT NULL COMMENT 'platform_warehouse, merchant_warehouse',
    `merchant_id` VARCHAR(26) NULL,
    `warehouse_id` VARCHAR(26) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, processing, shipped, delivered, cancelled, rejected, expired',
    `allocation_source` VARCHAR(20) NULL COMMENT 'auto, manual',
    `allocation_attempt` INT NOT NULL DEFAULT 1,
    `shipping_carrier` VARCHAR(50) NULL,
    `tracking_number` VARCHAR(100) NULL,
    `tracking_url` VARCHAR(500) NULL,
    `notified_at` DATETIME NULL,
    `shipped_at` DATETIME NULL,
    `delivered_at` DATETIME NULL,
    `cancelled_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `rejected_at` DATETIME NULL,
    `rejection_reason` TEXT NULL,
    `deadline_at` DATETIME NULL,
    `excluded_merchant_ids` JSON NULL,
    `cancel_reason` TEXT NULL,
    `remark` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_fulfillment_order` (`order_id`),
    INDEX `idx_fulfillment_type` (`fulfillment_type`),
    INDEX `idx_fulfillment_status` (`status`),
    INDEX `idx_fulfillment_merchant` (`merchant_id`),
    INDEX `idx_fulfillment_warehouse` (`warehouse_id`),
    INDEX `idx_fulfillment_allocation` (`allocation_source`, `allocation_attempt`),
    INDEX `idx_fulfillment_deadline` (`deadline_at`),
    CONSTRAINT `fk_fulfillment_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
    CONSTRAINT `fk_fulfillment_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fulfillment_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fulfillments';

-- -----------------------------------------------------------------------------
-- fulfillment_items
-- -----------------------------------------------------------------------------
CREATE TABLE `fulfillment_items` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `fulfillment_id` VARCHAR(26) NOT NULL,
    `order_item_id` VARCHAR(26) NOT NULL,
    `merchant_id` VARCHAR(26) NULL,
    `warehouse_id` VARCHAR(26) NULL,
    `channel_product_source_id` VARCHAR(26) NULL,
    `inventory_listing_id` VARCHAR(26) NULL,
    `merchant_inventory_id` VARCHAR(26) NULL,
    `quantity` INT NOT NULL,
    `list_price` DECIMAL(10, 2) NULL,
    `settlement_price` DECIMAL(10, 2) NULL,
    `commission_rate` DECIMAL(5, 2) NULL,
    `commission_amount` DECIMAL(10, 2) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_fulfillment_item_fulfillment` (`fulfillment_id`),
    INDEX `idx_fulfillment_item_order_item` (`order_item_id`),
    INDEX `idx_fulfillment_item_merchant` (`merchant_id`),
    INDEX `idx_fulfillment_item_warehouse` (`warehouse_id`),
    INDEX `idx_fulfillment_item_source` (`channel_product_source_id`),
    INDEX `idx_fulfillment_item_listing` (`inventory_listing_id`),
    INDEX `idx_fulfillment_item_inventory` (`merchant_inventory_id`),
    CONSTRAINT `fk_fulfillment_item_fulfillment` FOREIGN KEY (`fulfillment_id`) REFERENCES `fulfillments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fulfillment_item_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`),
    CONSTRAINT `fk_fulfillment_item_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fulfillment_item_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fulfillment_item_source` FOREIGN KEY (`channel_product_source_id`) REFERENCES `channel_product_sources` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fulfillment_item_listing` FOREIGN KEY (`inventory_listing_id`) REFERENCES `inventory_listings` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_fulfillment_item_inventory` FOREIGN KEY (`merchant_inventory_id`) REFERENCES `merchant_inventories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fulfillment Items';

-- -----------------------------------------------------------------------------
-- fulfillment_allocation_logs
-- -----------------------------------------------------------------------------
CREATE TABLE `fulfillment_allocation_logs` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `order_id` VARCHAR(26) NOT NULL,
    `order_item_id` VARCHAR(26) NULL,
    `attempt_number` INT NOT NULL,
    `selected_merchant_id` VARCHAR(26) NULL,
    `selected_source_id` VARCHAR(26) NULL,
    `result` VARCHAR(20) NOT NULL COMMENT 'success, no_stock, price_invalid, rejected, expired, no_source',
    `candidate_sources` JSON NULL,
    `failure_reason` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_fal_order` (`order_id`),
    INDEX `idx_fal_order_item` (`order_item_id`),
    INDEX `idx_fal_merchant` (`selected_merchant_id`),
    INDEX `idx_fal_result` (`result`),
    INDEX `idx_fal_created` (`created_at`),
    CONSTRAINT `fk_fal_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fal_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fal_merchant` FOREIGN KEY (`selected_merchant_id`) REFERENCES `merchants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fulfillment allocation audit logs';

-- =============================================================================
-- PHASE 13: Outbound Orders
-- =============================================================================

-- -----------------------------------------------------------------------------
-- outbound_orders
-- -----------------------------------------------------------------------------
CREATE TABLE `outbound_orders` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `outbound_no` VARCHAR(30) NOT NULL UNIQUE,
    `fulfillment_id` VARCHAR(26) NULL,
    `warehouse_id` VARCHAR(26) NOT NULL,
    `merchant_id` VARCHAR(26) NOT NULL,
    `outbound_type` VARCHAR(30) NOT NULL DEFAULT 'sales' COMMENT 'sales, return_to_merchant, transfer, scrap',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, picking, packing, ready, shipped, cancelled',
    `sync_status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, synced, failed, callback',
    `external_id` VARCHAR(100) NULL COMMENT 'WMS outbound ID',
    `sync_error` TEXT NULL,
    `sync_attempts` INT NOT NULL DEFAULT 0,
    `synced_at` DATETIME NULL,
    `last_sync_at` DATETIME NULL,
    `receiver_name` VARCHAR(50) NOT NULL,
    `receiver_phone` VARCHAR(30) NOT NULL,
    `receiver_address` VARCHAR(500) NOT NULL,
    `receiver_postal_code` VARCHAR(20) NULL,
    `shipping_carrier` VARCHAR(50) NULL,
    `tracking_number` VARCHAR(100) NULL,
    `picking_started_at` DATETIME NULL,
    `picking_completed_at` DATETIME NULL,
    `packing_started_at` DATETIME NULL,
    `packing_completed_at` DATETIME NULL,
    `shipped_at` DATETIME NULL,
    `cancelled_at` DATETIME NULL,
    `cancel_reason` TEXT NULL,
    `remark` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_outbound_merchant` (`merchant_id`),
    INDEX `idx_outbound_fulfillment` (`fulfillment_id`),
    INDEX `idx_outbound_warehouse` (`warehouse_id`),
    INDEX `idx_outbound_status` (`status`),
    INDEX `idx_outbound_sync_status` (`sync_status`),
    INDEX `idx_outbound_external` (`external_id`),
    CONSTRAINT `fk_outbound_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`),
    CONSTRAINT `fk_outbound_fulfillment` FOREIGN KEY (`fulfillment_id`) REFERENCES `fulfillments` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_outbound_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Outbound orders';

-- -----------------------------------------------------------------------------
-- outbound_order_items
-- -----------------------------------------------------------------------------
CREATE TABLE `outbound_order_items` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `outbound_order_id` VARCHAR(26) NOT NULL,
    `merchant_id` VARCHAR(26) NULL,
    `warehouse_id` VARCHAR(26) NULL,
    `product_sku_id` VARCHAR(26) NULL,
    `sku_name` VARCHAR(255) NULL,
    `style_number` VARCHAR(255) NULL,
    `color_name` VARCHAR(255) NULL,
    `product_name` VARCHAR(255) NULL,
    `product_image` VARCHAR(500) NULL,
    `stock_type` VARCHAR(20) NOT NULL DEFAULT 'normal' COMMENT 'normal or damaged',
    `quantity` INT NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_outbound_item_outbound` (`outbound_order_id`),
    INDEX `idx_outbound_item_merchant` (`merchant_id`),
    INDEX `idx_outbound_item_warehouse` (`warehouse_id`),
    INDEX `idx_outbound_item_sku` (`product_sku_id`),
    CONSTRAINT `fk_outbound_item_outbound` FOREIGN KEY (`outbound_order_id`) REFERENCES `outbound_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_outbound_item_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_outbound_item_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_outbound_item_sku` FOREIGN KEY (`product_sku_id`) REFERENCES `product_skus` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Outbound order items';

-- =============================================================================
-- PHASE 14: Settlements & Payouts
-- =============================================================================

-- -----------------------------------------------------------------------------
-- settlements
-- -----------------------------------------------------------------------------
CREATE TABLE `settlements` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `settlement_no` VARCHAR(30) NOT NULL UNIQUE COMMENT 'ST...',
    `merchant_id` VARCHAR(26) NOT NULL,
    `fulfillment_id` VARCHAR(26) NOT NULL UNIQUE,
    `order_id` VARCHAR(26) NOT NULL,
    `gross_amount` DECIMAL(12,2) NOT NULL,
    `commission_rate` DECIMAL(5,2) NOT NULL,
    `commission_amount` DECIMAL(12,2) NOT NULL,
    `net_amount` DECIMAL(12,2) NOT NULL,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'CNY',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, settled, cancelled',
    `settlement_days` INT NOT NULL DEFAULT 7,
    `scheduled_settle_at` DATETIME NOT NULL,
    `settled_at` DATETIME NULL,
    `cancelled_at` DATETIME NULL,
    `cancel_reason` VARCHAR(255) NULL,
    `wallet_transaction_id` VARCHAR(26) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_settlement_merchant` (`merchant_id`),
    INDEX `idx_settlement_fulfillment` (`fulfillment_id`),
    INDEX `idx_settlement_status` (`status`),
    INDEX `idx_settlement_scheduled` (`scheduled_settle_at`),
    INDEX `idx_settlement_no` (`settlement_no`),
    CONSTRAINT `fk_settlement_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`),
    CONSTRAINT `fk_settlement_fulfillment` FOREIGN KEY (`fulfillment_id`) REFERENCES `fulfillments` (`id`),
    CONSTRAINT `fk_settlement_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Settlements';

-- -----------------------------------------------------------------------------
-- settlement_items
-- -----------------------------------------------------------------------------
CREATE TABLE `settlement_items` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `settlement_id` VARCHAR(26) NOT NULL,
    `fulfillment_item_id` VARCHAR(26) NOT NULL,
    `sku_code` VARCHAR(100) NULL,
    `product_name` VARCHAR(255) NULL,
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `gross_amount` DECIMAL(12,2) NOT NULL,
    `commission_rate` DECIMAL(5,2) NOT NULL,
    `commission_amount` DECIMAL(10,2) NOT NULL,
    `net_amount` DECIMAL(12,2) NOT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_si_settlement` (`settlement_id`),
    INDEX `idx_si_fulfillment_item` (`fulfillment_item_id`),
    CONSTRAINT `fk_si_settlement` FOREIGN KEY (`settlement_id`) REFERENCES `settlements` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_si_fulfillment_item` FOREIGN KEY (`fulfillment_item_id`) REFERENCES `fulfillment_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Settlement items';

-- -----------------------------------------------------------------------------
-- payouts
-- -----------------------------------------------------------------------------
CREATE TABLE `payouts` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `payout_no` VARCHAR(30) NOT NULL UNIQUE COMMENT 'WD...',
    `merchant_id` VARCHAR(26) NOT NULL,
    `bank_account_id` VARCHAR(26) NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `actual_amount` DECIMAL(12,2) NOT NULL,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'CNY',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, approved, processing, completed, rejected, failed',
    `bank_name` VARCHAR(100) NOT NULL,
    `account_number` VARCHAR(50) NOT NULL,
    `account_holder` VARCHAR(100) NOT NULL,
    `bank_code` VARCHAR(20) NULL,
    `approved_at` DATETIME NULL,
    `processing_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `rejected_at` DATETIME NULL,
    `failed_at` DATETIME NULL,
    `reviewed_by` VARCHAR(26) NULL,
    `reject_reason` VARCHAR(255) NULL,
    `fail_reason` VARCHAR(255) NULL,
    `remark` TEXT NULL,
    `external_transaction_id` VARCHAR(100) NULL,
    `wallet_transaction_id` VARCHAR(26) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_payout_merchant` (`merchant_id`),
    INDEX `idx_payout_status` (`status`),
    INDEX `idx_payout_no` (`payout_no`),
    INDEX `idx_payout_bank_account` (`bank_account_id`),
    CONSTRAINT `fk_payout_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`),
    CONSTRAINT `fk_payout_bank_account` FOREIGN KEY (`bank_account_id`) REFERENCES `merchant_bank_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payouts';

-- =============================================================================
-- PHASE 15: API Keys & Webhooks
-- =============================================================================

-- -----------------------------------------------------------------------------
-- api_keys
-- -----------------------------------------------------------------------------
CREATE TABLE `api_keys` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `key_id` VARCHAR(32) NOT NULL COMMENT 'Public API Key ID (prefix: dwl_)',
    `key_secret` VARCHAR(64) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `type` VARCHAR(20) NOT NULL COMMENT 'warehouse or merchant',
    `warehouse_id` VARCHAR(26) NULL,
    `merchant_id` VARCHAR(26) NULL,
    `permissions` JSON NOT NULL,
    `ip_whitelist` JSON NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, suspended, revoked',
    `last_used_at` DATETIME NULL,
    `expires_at` DATETIME NULL,
    `created_by` VARCHAR(26) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE KEY `uk_api_key_key_id` (`key_id`),
    INDEX `idx_api_key_type` (`type`),
    INDEX `idx_api_key_status` (`status`),
    INDEX `idx_api_key_warehouse` (`warehouse_id`),
    INDEX `idx_api_key_merchant` (`merchant_id`),
    CONSTRAINT `fk_api_key_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_api_key_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_api_key_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Open API Keys';

-- -----------------------------------------------------------------------------
-- api_key_logs
-- -----------------------------------------------------------------------------
CREATE TABLE `api_key_logs` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `api_key_id` VARCHAR(26) NOT NULL,
    `endpoint` VARCHAR(255) NOT NULL,
    `method` VARCHAR(10) NOT NULL,
    `request_id` VARCHAR(36) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(500) NULL,
    `request_body_size` INT UNSIGNED NULL,
    `status_code` SMALLINT UNSIGNED NOT NULL,
    `error_code` VARCHAR(50) NULL,
    `response_time_ms` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_api_key_log_api_key` (`api_key_id`),
    INDEX `idx_api_key_log_request_id` (`request_id`),
    INDEX `idx_api_key_log_created_at` (`created_at`),
    INDEX `idx_api_key_log_endpoint` (`endpoint`(100)),
    INDEX `idx_api_key_log_status_code` (`status_code`),
    CONSTRAINT `fk_api_key_log_api_key` FOREIGN KEY (`api_key_id`) REFERENCES `api_keys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API Call Audit Logs';

-- -----------------------------------------------------------------------------
-- webhooks
-- -----------------------------------------------------------------------------
CREATE TABLE `webhooks` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `api_key_id` VARCHAR(26) NOT NULL,
    `url` VARCHAR(500) NOT NULL,
    `events` JSON NOT NULL,
    `secret` VARCHAR(64) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, suspended, failed',
    `failure_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `last_triggered_at` DATETIME NULL,
    `last_success_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_webhook_api_key` (`api_key_id`),
    INDEX `idx_webhook_status` (`status`),
    CONSTRAINT `fk_webhook_api_key` FOREIGN KEY (`api_key_id`) REFERENCES `api_keys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Webhook Subscriptions';

-- -----------------------------------------------------------------------------
-- webhook_deliveries
-- -----------------------------------------------------------------------------
CREATE TABLE `webhook_deliveries` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `webhook_id` VARCHAR(26) NOT NULL,
    `event` VARCHAR(50) NOT NULL,
    `event_id` VARCHAR(36) NOT NULL,
    `payload` JSON NOT NULL,
    `response_code` SMALLINT UNSIGNED NULL,
    `response_body` TEXT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, delivered, failed',
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `next_retry_at` DATETIME NULL,
    `delivered_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_webhook_delivery_webhook` (`webhook_id`),
    INDEX `idx_webhook_delivery_event_id` (`event_id`),
    INDEX `idx_webhook_delivery_status` (`status`),
    INDEX `idx_webhook_delivery_next_retry` (`next_retry_at`),
    INDEX `idx_webhook_delivery_created_at` (`created_at`),
    CONSTRAINT `fk_webhook_delivery_webhook` FOREIGN KEY (`webhook_id`) REFERENCES `webhooks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Webhook Delivery Logs';

-- =============================================================================
-- Re-enable foreign key checks
-- =============================================================================
SET FOREIGN_KEY_CHECKS = 1;
