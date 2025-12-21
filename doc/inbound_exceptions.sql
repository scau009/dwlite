-- Entity: App\Entity\InboundException
-- Description: Inbound quality/quantity exceptions

CREATE TABLE `inbound_exceptions` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `exception_no` VARCHAR(32) NOT NULL UNIQUE COMMENT 'Exception number (EX...)',
    `inbound_order_id` VARCHAR(26) NOT NULL,
    `merchant_id` VARCHAR(26) NOT NULL,
    `warehouse_id` VARCHAR(26) NOT NULL,
    `type` VARCHAR(30) NOT NULL COMMENT 'quantity_short, quantity_over, damaged, wrong_item, quality_issue, packaging, expired, other',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, processing, resolved, closed',

    -- Quantity summary
    `total_quantity` INT NOT NULL DEFAULT 0 COMMENT 'Total exception quantity',

    -- Description and evidence
    `description` TEXT NOT NULL COMMENT 'Exception description',
    `evidence_images` JSON NULL COMMENT 'Evidence image URLs',

    -- Resolution info
    `resolution` VARCHAR(30) NULL COMMENT 'accept, reject, claim, recount, partial_accept',
    `resolution_notes` TEXT NULL COMMENT 'Resolution notes',
    `claim_amount` DECIMAL(10,2) NULL COMMENT 'Claim amount',
    `resolved_at` DATETIME NULL COMMENT 'Resolved time',

    -- Communication log
    `communication_log` JSON NULL COMMENT 'Communication history: [{time, from, content}]',

    -- Operators
    `reported_by` VARCHAR(26) NULL COMMENT 'Reporter user ID',
    `resolved_by` VARCHAR(26) NULL COMMENT 'Resolver user ID',

    -- Timestamps
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,

    -- Indexes
    INDEX `idx_inbound_exc_no` (`exception_no`),
    INDEX `idx_inbound_exc_order` (`inbound_order_id`),
    INDEX `idx_inbound_exc_merchant` (`merchant_id`),
    INDEX `idx_inbound_exc_status` (`status`),
    INDEX `idx_inbound_exc_created` (`created_at`),

    -- Foreign Keys
    CONSTRAINT `fk_inbound_exc_order` FOREIGN KEY (`inbound_order_id`) REFERENCES `inbound_orders` (`id`),
    CONSTRAINT `fk_inbound_exc_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`),
    CONSTRAINT `fk_inbound_exc_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inbound exceptions';
