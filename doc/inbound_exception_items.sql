-- Entity: App\Entity\InboundExceptionItem
-- Description: Inbound exception line items

CREATE TABLE `inbound_exception_items` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `inbound_exception_id` VARCHAR(26) NOT NULL,
    `inbound_order_item_id` VARCHAR(26) NULL COMMENT 'Reference to original order item',

    -- SKU snapshot fields
    `sku_name` VARCHAR(255) NULL COMMENT 'SKU name (size unit + size value)',
    `color_name` VARCHAR(255) NULL COMMENT 'Color name from product',
    `product_name` VARCHAR(255) NULL COMMENT 'Product name snapshot',
    `product_image` VARCHAR(500) NULL COMMENT 'Product image URL snapshot',

    -- Quantity info
    `quantity` INT NOT NULL COMMENT 'Exception quantity',

    -- Timestamps
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,

    -- Indexes
    INDEX `idx_exception_item_exception` (`inbound_exception_id`),
    INDEX `idx_exception_item_order_item` (`inbound_order_item_id`),

    -- Foreign Keys
    CONSTRAINT `fk_exception_item_exception` FOREIGN KEY (`inbound_exception_id`) REFERENCES `inbound_exceptions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_exception_item_order_item` FOREIGN KEY (`inbound_order_item_id`) REFERENCES `inbound_order_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inbound exception items';
