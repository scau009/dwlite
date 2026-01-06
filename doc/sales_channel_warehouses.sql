-- Entity: App\Entity\SalesChannelWarehouse
-- Description: Sales channel fulfillment warehouses configuration

CREATE TABLE `sales_channel_warehouses` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `sales_channel_id` VARCHAR(26) NOT NULL COMMENT 'Sales channel ID',
    `warehouse_id` VARCHAR(26) NOT NULL COMMENT 'Warehouse ID',
    `priority` INT NOT NULL DEFAULT 0 COMMENT 'Priority order, lower number = higher priority',
    `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, disabled',
    `remark` VARCHAR(255) NULL COMMENT 'Remark or notes',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,

    UNIQUE INDEX `uk_channel_warehouse` (`sales_channel_id`, `warehouse_id`),
    INDEX `idx_scw_channel` (`sales_channel_id`),
    INDEX `idx_scw_warehouse` (`warehouse_id`),
    INDEX `idx_scw_priority` (`priority`),
    INDEX `idx_scw_status` (`status`),

    CONSTRAINT `fk_scw_channel` FOREIGN KEY (`sales_channel_id`)
        REFERENCES `sales_channels` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_scw_warehouse` FOREIGN KEY (`warehouse_id`)
        REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Sales channel fulfillment warehouses';