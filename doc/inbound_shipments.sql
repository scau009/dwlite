-- Entity: App\Entity\InboundShipment
-- Description: Shipping/logistics info for inbound orders

CREATE TABLE `inbound_shipments` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `inbound_order_id` VARCHAR(26) NOT NULL UNIQUE,

    -- Carrier info
    `carrier_code` VARCHAR(20) NOT NULL COMMENT 'Carrier code: SF, JD, ZTO, etc.',
    `carrier_name` VARCHAR(50) NULL COMMENT 'Carrier name',
    `tracking_number` VARCHAR(50) NOT NULL COMMENT 'Tracking number',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, picked, in_transit, delivered, exception',

    -- Sender info
    `sender_name` VARCHAR(50) NOT NULL COMMENT 'Sender name',
    `sender_phone` VARCHAR(20) NOT NULL COMMENT 'Sender phone',
    `sender_address` VARCHAR(255) NOT NULL COMMENT 'Sender address',
    `sender_province` VARCHAR(50) NULL COMMENT 'Sender province',
    `sender_city` VARCHAR(50) NULL COMMENT 'Sender city',

    -- Package info
    `box_count` INT NOT NULL DEFAULT 1 COMMENT 'Number of boxes/packages',
    `total_weight` DECIMAL(10,2) NULL COMMENT 'Total weight in kg',
    `total_volume` DECIMAL(10,2) NULL COMMENT 'Total volume in m³',

    -- Time milestones
    `shipped_at` DATETIME NOT NULL COMMENT 'Shipped time',
    `estimated_arrival_date` DATE NULL COMMENT 'Estimated arrival date',
    `delivered_at` DATETIME NULL COMMENT 'Delivered time',

    -- Tracking
    `tracking_history` JSON NULL COMMENT 'Tracking events: [{time, status, location, desc}]',

    -- Notes
    `notes` TEXT NULL COMMENT 'Notes',

    -- Timestamps
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,

    -- Indexes
    INDEX `idx_inbound_shipment_order` (`inbound_order_id`),
    INDEX `idx_inbound_shipment_tracking` (`tracking_number`),
    INDEX `idx_inbound_shipment_carrier` (`carrier_code`),

    -- Foreign Keys
    CONSTRAINT `fk_inbound_shipment_order` FOREIGN KEY (`inbound_order_id`) REFERENCES `inbound_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inbound shipments';
