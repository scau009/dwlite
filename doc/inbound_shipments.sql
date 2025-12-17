-- Entity: App\Entity\InboundShipment
-- Description: Shipping/logistics info for inbound orders

CREATE TABLE `inbound_shipments` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `inbound_order_id` VARCHAR(26) NOT NULL UNIQUE,
    `carrierCode` VARCHAR(20) NOT NULL COMMENT 'SF, JD, ZTO, etc.',
    `carrierName` VARCHAR(50) NULL,
    `trackingNumber` VARCHAR(50) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, picked, in_transit, delivered, exception',
    `senderName` VARCHAR(50) NOT NULL,
    `senderPhone` VARCHAR(20) NOT NULL,
    `senderAddress` VARCHAR(255) NOT NULL,
    `senderProvince` VARCHAR(50) NULL,
    `senderCity` VARCHAR(50) NULL,
    `boxCount` INT NOT NULL DEFAULT 1,
    `totalWeight` DECIMAL(10,2) NULL COMMENT 'kg',
    `totalVolume` DECIMAL(10,2) NULL COMMENT 'm³',
    `shippedAt` DATETIME NOT NULL,
    `estimatedArrivalDate` DATE NULL,
    `deliveredAt` DATETIME NULL,
    `trackingHistory` JSON NULL COMMENT 'Tracking events',
    `notes` TEXT NULL,
    `createdAt` DATETIME NOT NULL,
    `updatedAt` DATETIME NOT NULL,
    INDEX `idx_inbound_shipment_order` (`inbound_order_id`),
    INDEX `idx_inbound_shipment_tracking` (`trackingNumber`),
    INDEX `idx_inbound_shipment_carrier` (`carrierCode`),
    CONSTRAINT `fk_inbound_shipment_order` FOREIGN KEY (`inbound_order_id`) REFERENCES `inbound_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inbound shipments';