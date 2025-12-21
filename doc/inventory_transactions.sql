-- Entity: App\Entity\InventoryTransaction
-- Description: 库存流水 - 记录每一次库存变动

-- 变动类型 (type):
--   inbound_transit   - 入库在途（发货时）
--   inbound_stock     - 入库上架（仓库收货后）
--   inbound_damaged   - 入库损坏
--   outbound_reserve  - 出库锁定（订单占用）
--   outbound_release  - 出库释放（订单取消）
--   outbound_ship     - 出库发货
--   adjustment_add    - 盘点增加
--   adjustment_sub    - 盘点减少
--   transfer_out      - 调拨出库
--   transfer_in       - 调拨入库
--   damage            - 损坏报废
--   return_inbound    - 退货入库

-- 关联单据类型 (reference_type):
--   inbound_order     - 送仓单
--   sales_order       - 销售订单
--   return_order      - 退货单
--   adjustment        - 盘点单
--   transfer          - 调拨单

CREATE TABLE `inventory_transactions` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_inventory_id` VARCHAR(26) NOT NULL,

    `type` VARCHAR(30) NOT NULL COMMENT '变动类型',

    -- 数量变动（正数增加，负数减少）
    `quantity` INT NOT NULL COMMENT '变动数量',

    -- 变动前后余额
    `stock_type` VARCHAR(20) NOT NULL COMMENT '影响的库存类型：in_transit, available, reserved, damaged',
    `balance_before` INT NOT NULL COMMENT '变动前余额',
    `balance_after` INT NOT NULL COMMENT '变动后余额',

    -- 关联单据
    `reference_type` VARCHAR(30) NULL COMMENT '关联单据类型',
    `reference_id` VARCHAR(26) NULL COMMENT '关联单据 ID',
    `reference_no` VARCHAR(50) NULL COMMENT '关联单据编号（便于展示）',

    -- 成本信息
    `unit_cost` DECIMAL(10,2) NULL COMMENT '单件成本',

    -- 操作信息
    `operator_id` VARCHAR(26) NULL COMMENT '操作人 ID',
    `operator_name` VARCHAR(50) NULL COMMENT '操作人名称',
    `notes` TEXT NULL COMMENT '备注',

    `created_at` DATETIME NOT NULL,

    -- Indexes
    INDEX `idx_inv_trans_inventory` (`merchant_inventory_id`),
    INDEX `idx_inv_trans_type` (`type`),
    INDEX `idx_inv_trans_reference` (`reference_type`, `reference_id`),
    INDEX `idx_inv_trans_created` (`created_at`),

    -- Foreign keys
    CONSTRAINT `fk_inv_trans_inventory` FOREIGN KEY (`merchant_inventory_id`) REFERENCES `merchant_inventories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='库存流水';