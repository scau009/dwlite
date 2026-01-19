-- Entity: App\Entity\InventoryReservation
-- Description: Two-layer inventory reservation for preventing overselling

CREATE TABLE `inventory_reservations` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',

    -- Layer 1: ChannelProduct level reservation (on order confirmation)
    `channel_product_id` VARCHAR(26) NOT NULL COMMENT 'Channel product ID',
    `order_id` VARCHAR(26) NOT NULL COMMENT 'Order ID',
    `order_item_id` VARCHAR(26) NOT NULL COMMENT 'Order item ID',
    `quantity` INT NOT NULL COMMENT 'Reserved quantity',

    -- Layer 2: MerchantInventory level lock (after fulfillment allocation)
    `inventory_id` VARCHAR(26) NULL COMMENT 'Actual inventory ID (filled after allocation)',
    `fulfillment_id` VARCHAR(26) NULL COMMENT 'Fulfillment ID (filled after allocation)',
    `fulfillment_item_id` VARCHAR(26) NULL COMMENT 'Fulfillment item ID (filled after allocation)',

    -- Status
    `status` VARCHAR(20) NOT NULL DEFAULT 'reserved' COMMENT 'reserved, allocated, locked, released, expired, completed',

    -- Timestamps
    `expires_at` DATETIME NOT NULL COMMENT 'Reservation expiry time (UTC)',
    `allocated_at` DATETIME NULL COMMENT 'Allocation time',
    `locked_at` DATETIME NULL COMMENT 'Lock time',
    `released_at` DATETIME NULL COMMENT 'Release time',
    `completed_at` DATETIME NULL COMMENT 'Completion time',

    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,

    INDEX `idx_reservation_channel_product` (`channel_product_id`, `status`),
    INDEX `idx_reservation_inventory` (`inventory_id`, `status`),
    INDEX `idx_reservation_order` (`order_id`),
    INDEX `idx_reservation_order_item` (`order_item_id`),
    INDEX `idx_reservation_fulfillment` (`fulfillment_id`),
    INDEX `idx_reservation_expires` (`expires_at`, `status`),
    INDEX `idx_reservation_status` (`status`),

    CONSTRAINT `fk_reservation_channel_product` FOREIGN KEY (`channel_product_id`) REFERENCES `channel_products` (`id`),
    CONSTRAINT `fk_reservation_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
    CONSTRAINT `fk_reservation_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`),
    CONSTRAINT `fk_reservation_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `merchant_inventories` (`id`),
    CONSTRAINT `fk_reservation_fulfillment` FOREIGN KEY (`fulfillment_id`) REFERENCES `fulfillments` (`id`),
    CONSTRAINT `fk_reservation_fulfillment_item` FOREIGN KEY (`fulfillment_item_id`) REFERENCES `fulfillment_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory reservations (two-layer)';
