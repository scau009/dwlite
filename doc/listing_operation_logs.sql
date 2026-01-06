-- Listing Operation Logs Table
-- 上架操作日志表，记录所有上架相关的敏感操作

CREATE TABLE `listing_operation_logs` (
  `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID主键',
  `listing_id` VARCHAR(26) NOT NULL COMMENT '上架ID',
  `merchant_id` VARCHAR(26) NOT NULL COMMENT '商户ID',
  `operator_id` VARCHAR(26) NOT NULL COMMENT '操作人ID',
  `operation` VARCHAR(50) NOT NULL COMMENT '操作类型: create, update_price, update_compare_price, update_allocation, update_remark, activate, pause, delete',
  `changes` JSON NULL COMMENT '变更内容 {before: {...}, after: {...}}',
  `created_at` DATETIME NOT NULL COMMENT '操作时间(UTC)',
  INDEX `idx_listing_id` (`listing_id`),
  INDEX `idx_merchant_id` (`merchant_id`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_operation` (`operation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='上架操作日志';
