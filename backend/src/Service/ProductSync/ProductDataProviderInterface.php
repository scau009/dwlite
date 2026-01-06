<?php

namespace App\Service\ProductSync;

use App\Service\ProductSync\Dto\ExternalProductDto;
use App\Service\ProductSync\Dto\PaginatedResultDto;

/**
 * Interface for external product data providers.
 *
 * This interface abstracts the data provider implementation, allowing
 * the sync service to work with different data sources (KicksDB, GOAT, etc.)
 * without changing the core sync logic.
 */
interface ProductDataProviderInterface
{
    /**
     * Get the unique identifier for this provider.
     *
     * This is used to identify the provider in the database and configuration.
     * Example: 'kicksdb', 'goat', 'stockx'
     */
    public function getProviderName(): string;

    /**
     * Fetch a page of products from the external source.
     *
     * @param int $page     Page number (1-based)
     * @param int $pageSize Number of products per page (max varies by provider)
     *
     * @return PaginatedResultDto Contains products and pagination metadata
     */
    public function fetchProducts(int $page, int $pageSize = 100): PaginatedResultDto;

    /**
     * Fetch a single product by its external ID.
     *
     * This is optional - providers may return null if not supported.
     *
     * @param string $externalId The product ID in the external system
     */
    public function fetchProduct(string $externalId): ?ExternalProductDto;

    /**
     * Get the default currency for prices from this provider.
     *
     * Example: 'USD', 'EUR', 'CNY'
     */
    public function getCurrency(): string;

    /**
     * Get the base URL for the external system.
     *
     * Used for generating external product URLs.
     */
    public function getBaseUrl(): string;
}
