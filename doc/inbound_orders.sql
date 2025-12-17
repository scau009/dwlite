-- Entity: App\Entity\InboundOrder
-- Description: Inbound orders (merchant to warehouse delivery orders)

CREATE TABLE `inbound_orders` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `orderNo` VARCHAR(32) NOT NULL UNIQUE COMMENT 'Inbound order number (IB...)',
    `merchant_id` VARCHAR(26) NOT NULL,
    `warehouse_id` VARCHAR(26) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'draft, pending, shipped, arrived, receiving, completed, partial_completed, cancelled',
    `totalSkuCount` INT NOT NULL DEFAULT 0,
    `totalQuantity` INT NOT NULL DEFAULT 0 COMMENT 'Expected total',
    `receivedQuantity` INT NOT NULL DEFAULT 0 COMMENT 'Received total',
    `expectedArrivalDate` DATE NULL,
    `submittedAt` DATETIME NULL,
    `shippedAt` DATETIME NULL,
    `arrivedAt` DATETIME NULL,
    `completedAt` DATETIME NULL,
    `cancelledAt` DATETIME NULL,
    `merchantNotes` TEXT NULL,
    `warehouseNotes` TEXT NULL,
    `cancelReason` VARCHAR(100) NULL,
    `createdAt` DATETIME NOT NULL,
    `updatedAt` DATETIME NOT NULL,
    INDEX `idx_inbound_order_no` (`orderNo`),
    INDEX `idx_inbound_merchant` (`merchant_id`),
    INDEX `idx_inbound_warehouse` (`warehouse_id`),
    INDEX `idx_inbound_status` (`status`),
    INDEX `idx_inbound_created` (`createdAt`),
    CONSTRAINT `fk_inbound_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`),
    CONSTRAINT `fk_inbound_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inbound orders';