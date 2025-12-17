-- Entity: App\Entity\InventoryTransaction
-- Description: Inventory change records for audit trail

CREATE TABLE `inventory_transactions` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `merchant_inventory_id` VARCHAR(26) NOT NULL,
    `type` VARCHAR(30) NOT NULL COMMENT 'inbound, outbound, reserve, release, adjust, damage',
    `quantity` INT NOT NULL COMMENT 'Change amount (positive or negative)',
    `available_before` INT NOT NULL COMMENT 'Available quantity before',
    `available_after` INT NOT NULL COMMENT 'Available quantity after',
    `reserved_before` INT NOT NULL COMMENT 'Reserved quantity before',
    `reserved_after` INT NOT NULL COMMENT 'Reserved quantity after',
    `reference_type` VARCHAR(50) NULL COMMENT 'Related entity type',
    `reference_id` VARCHAR(26) NULL COMMENT 'Related entity ID',
    `notes` VARCHAR(255) NULL,
    `operator_id` VARCHAR(26) NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_it_inventory` (`merchant_inventory_id`),
    INDEX `idx_it_type` (`type`),
    INDEX `idx_it_reference` (`reference_type`, `reference_id`),
    INDEX `idx_it_created` (`created_at`),
    CONSTRAINT `fk_it_inventory` FOREIGN KEY (`merchant_inventory_id`) REFERENCES `merchant_inventories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory transactions';