-- Entity: App\Entity\MerchantInventory
-- Description: 商户库存 - 商户在某仓库的 SKU 库存

CREATE TABLE `merchant_inventories` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_id` VARCHAR(26) NOT NULL,
    `warehouse_id` VARCHAR(26) NOT NULL,
    `product_sku_id` VARCHAR(26) NOT NULL,

    -- 库存数量
    `quantity_in_transit` INT NOT NULL DEFAULT 0 COMMENT '在途数量（已发货未入库）',
    `quantity_available` INT NOT NULL DEFAULT 0 COMMENT '可用库存（可以被销售）',
    `quantity_reserved` INT NOT NULL DEFAULT 0 COMMENT '锁定库存（已被订单占用，待出库）',
    `quantity_damaged` INT NOT NULL DEFAULT 0 COMMENT '损坏库存（不可销售）',
    `quantity_allocated` INT NOT NULL DEFAULT 0 COMMENT '渠道独占分配的库存（全托管模式）',

    -- 成本信息
    `average_cost` DECIMAL(10,2) NULL COMMENT '平均成本单价（加权平均成本）',

    -- 安全库存
    `safety_stock` INT NULL COMMENT '安全库存量（低于此值预警）',

    -- 统计信息
    `last_inbound_at` DATETIME NULL COMMENT '最后入库时间',
    `last_outbound_at` DATETIME NULL COMMENT '最后出库时间',

    -- 库存同步相关（不送仓模式使用）
    `last_synced_at` DATETIME NULL COMMENT '最后同步时间（商家 API 同步）',
    `external_sku_id` VARCHAR(100) NULL COMMENT '商家系统的 SKU ID（用于 API 对接）',

    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,

    -- Indexes
    INDEX `idx_inventory_merchant` (`merchant_id`),
    INDEX `idx_inventory_warehouse` (`warehouse_id`),
    INDEX `idx_inventory_sku` (`product_sku_id`),

    -- Unique constraint
    UNIQUE INDEX `uniq_merchant_warehouse_sku` (`merchant_id`, `warehouse_id`, `product_sku_id`),

    -- Foreign keys
    CONSTRAINT `fk_mi_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mi_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mi_sku` FOREIGN KEY (`product_sku_id`) REFERENCES `product_skus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商户库存';