-- Entity: App\Entity\InboundOrderItem
-- Description: Inbound order line items

CREATE TABLE `inbound_order_items` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `inbound_order_id` VARCHAR(26) NOT NULL,
    `product_sku_id` VARCHAR(26) NULL,

    -- SKU snapshot fields
    `sku_name` VARCHAR(255) NULL COMMENT 'SKU name (size unit + size value)',
    `style_number` VARCHAR(255) NULL COMMENT 'Style number from product',
    `color_name` VARCHAR(255) NULL COMMENT 'Color name from product',
    `product_name` VARCHAR(255) NULL COMMENT 'Product name snapshot',
    `product_image` VARCHAR(500) NULL COMMENT 'Product image URL snapshot',

    -- Quantity info
    `expected_quantity` INT NOT NULL COMMENT 'Expected quantity',
    `received_quantity` INT NOT NULL DEFAULT 0 COMMENT 'Received quantity',
    `damaged_quantity` INT NOT NULL DEFAULT 0 COMMENT 'Damaged quantity (included in received)',

    -- Cost info
    `unit_cost` DECIMAL(10,2) NULL COMMENT 'Unit cost',

    -- Status
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, received, partial, over, missing',

    -- Warehouse feedback
    `warehouse_remark` TEXT NULL COMMENT 'Warehouse remark',
    `received_at` DATETIME NULL COMMENT 'Received time',

    -- Timestamps
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,

    -- Indexes
    INDEX `idx_inbound_item_order` (`inbound_order_id`),
    INDEX `idx_inbound_item_sku` (`product_sku_id`),
    UNIQUE INDEX `uniq_inbound_order_sku` (`inbound_order_id`, `product_sku_id`),

    -- Foreign Keys
    CONSTRAINT `fk_inbound_item_order` FOREIGN KEY (`inbound_order_id`) REFERENCES `inbound_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_inbound_item_sku` FOREIGN KEY (`product_sku_id`) REFERENCES `product_skus` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inbound order items';
