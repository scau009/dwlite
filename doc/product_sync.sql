-- =============================================================================
-- Product Sync Module - Database Schema
-- Description: Tables for product data synchronization from external sources
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Table: product_sync_jobs
-- Purpose: Track sync job status, progress, and statistics
-- -----------------------------------------------------------------------------
CREATE TABLE `product_sync_jobs` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `provider` VARCHAR(50) NOT NULL COMMENT 'Provider identifier: kicksdb, goat, etc.',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, running, completed, failed',
    `total_pages` INT NULL COMMENT 'Total pages to sync',
    `processed_pages` INT NOT NULL DEFAULT 0 COMMENT 'Pages processed so far',
    `total_products` INT NULL COMMENT 'Total products to sync',
    `synced_products` INT NOT NULL DEFAULT 0 COMMENT 'Products processed',
    `created_products` INT NOT NULL DEFAULT 0 COMMENT 'New products created',
    `updated_products` INT NOT NULL DEFAULT 0 COMMENT 'Existing products updated',
    `skipped_products` INT NOT NULL DEFAULT 0 COMMENT 'Products skipped (no styleId, etc.)',
    `failed_products` INT NOT NULL DEFAULT 0 COMMENT 'Products failed to sync',
    `error_message` TEXT NULL COMMENT 'Error details if failed',
    `started_at` DATETIME NULL COMMENT 'When sync started (UTC)',
    `completed_at` DATETIME NULL COMMENT 'When sync completed (UTC)',
    `created_at` DATETIME NOT NULL COMMENT 'Record creation time (UTC)',
    INDEX `idx_sync_job_provider` (`provider`),
    INDEX `idx_sync_job_status` (`status`),
    INDEX `idx_sync_job_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product sync jobs';

-- -----------------------------------------------------------------------------
-- Table: product_external_mappings
-- Purpose: Map products to external data sources (KicksDB, GOAT, etc.)
-- Design Notes:
--   - product_id + provider: One product can only have one mapping per provider
--   - provider + external_id: Prevent duplicate imports from same external product
--   - external_style_id: Used for matching existing products by style number
--   - external_data: Store raw JSON for debugging and data recovery
-- -----------------------------------------------------------------------------
CREATE TABLE `product_external_mappings` (
    `id` VARCHAR(26) NOT NULL PRIMARY KEY COMMENT 'ULID',
    `product_id` VARCHAR(26) NOT NULL COMMENT 'Reference to products.id',
    `provider` VARCHAR(50) NOT NULL COMMENT 'Data provider: kicksdb, goat, stockx, etc.',
    `external_id` VARCHAR(100) NOT NULL COMMENT 'Product ID in external system',
    `external_style_id` VARCHAR(100) NULL COMMENT 'Style ID in external system (e.g., styleId from KicksDB)',
    `external_url` VARCHAR(500) NULL COMMENT 'Product URL in external system',
    `external_data` JSON NULL COMMENT 'Raw data from external system for reference',
    `last_synced_at` DATETIME NOT NULL COMMENT 'Last sync timestamp (UTC)',
    `created_at` DATETIME NOT NULL COMMENT 'Record creation time (UTC)',
    `updated_at` DATETIME NOT NULL COMMENT 'Record update time (UTC)',
    UNIQUE KEY `uk_product_provider` (`product_id`, `provider`),
    UNIQUE KEY `uk_provider_external` (`provider`, `external_id`),
    INDEX `idx_mapping_provider` (`provider`),
    INDEX `idx_mapping_style_id` (`provider`, `external_style_id`),
    CONSTRAINT `fk_mapping_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product external source mappings';
