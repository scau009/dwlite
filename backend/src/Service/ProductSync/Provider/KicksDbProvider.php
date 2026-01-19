<?php

namespace App\Service\ProductSync\Provider;

use App\Service\ProductSync\Dto\ExternalProductDto;
use App\Service\ProductSync\Dto\PaginatedResultDto;
use App\Service\ProductSync\ProductDataProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * KicksDB implementation of the ProductDataProviderInterface.
 *
 * Provides access to StockX product data through the KicksDB API (v3).
 */
class KicksDbProvider implements ProductDataProviderInterface
{
    public const PROVIDER_NAME = 'kicksdb';
    public const BASE_URL = 'https://stockx.com';

    private string $market = KicksDbApiClient::MARKET_US;
    private string $currency = KicksDbApiClient::CURRENCY_USD;

    public function __construct(
        private KicksDbApiClient $apiClient,
        private LoggerInterface $logger,
    ) {
    }

    public function getProviderName(): string
    {
        return self::PROVIDER_NAME;
    }

    /**
     * Set the market for API requests.
     */
    public function setMarket(string $market): self
    {
        $this->market = $market;

        return $this;
    }

    /**
     * Set the currency for API requests.
     */
    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function fetchProducts(int $page, int $pageSize = 100): PaginatedResultDto
    {
        $this->logger->info('Fetching products from KicksDB', [
            'page' => $page,
            'page_size' => $pageSize,
            'market' => $this->market,
            'currency' => $this->currency,
        ]);

        $result = $this->apiClient->getStockXProducts(
            pageNumber: $page,
            pageSize: $pageSize,
            market: $this->market,
            currency: $this->currency,
        );

        $products = [];
        foreach ($result['products'] as $productData) {
            try {
                $products[] = ExternalProductDto::fromKicksDb($productData, $this->currency);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to parse product from KicksDB', [
                    'product_id' => $productData['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Fetched products from KicksDB', [
            'page' => $page,
            'fetched_count' => count($products),
            'total_count' => $result['totalCount'],
            'has_next_page' => $result['hasNextPage'],
        ]);

        return new PaginatedResultDto(
            products: $products,
            totalCount: $result['totalCount'],
            pageNumber: $result['pageNumber'],
            pageSize: $result['pageSize'],
            hasNextPage: $result['hasNextPage'],
        );
    }

    /**
     * Search products by query string.
     *
     * @param string $searchQuery Search term (e.g., "air jordan 1 bred")
     * @param int    $page        Page number
     * @param int    $pageSize    Items per page
     */
    public function searchProducts(string $searchQuery, int $page = 1, int $pageSize = 100): PaginatedResultDto
    {
        $this->logger->info('Searching products in KicksDB', [
            'query' => $searchQuery,
            'page' => $page,
            'page_size' => $pageSize,
        ]);

        $result = $this->apiClient->searchProducts(
            searchQuery: $searchQuery,
            pageNumber: $page,
            pageSize: $pageSize,
            market: $this->market,
            currency: $this->currency,
        );

        $products = [];
        foreach ($result['products'] as $productData) {
            try {
                $products[] = ExternalProductDto::fromKicksDb($productData, $this->currency);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to parse product from KicksDB search', [
                    'product_id' => $productData['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return new PaginatedResultDto(
            products: $products,
            totalCount: $result['totalCount'],
            pageNumber: $result['pageNumber'],
            pageSize: $result['pageSize'],
            hasNextPage: $result['hasNextPage'],
        );
    }

    public function fetchProduct(string $externalId): ?ExternalProductDto
    {
        $this->logger->info('Fetching single product from KicksDB', [
            'external_id' => $externalId,
            'market' => $this->market,
            'currency' => $this->currency,
        ]);

        $data = $this->apiClient->getStockXProduct(
            productId: $externalId,
            market: $this->market,
            currency: $this->currency,
        );

        if ($data === null) {
            return null;
        }

        try {
            return ExternalProductDto::fromKicksDb($data, $this->currency);
        } catch (\Exception $e) {
            $this->logger->error('Failed to parse product from KicksDB', [
                'external_id' => $externalId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getBaseUrl(): string
    {
        return self::BASE_URL;
    }

    /**
     * Fetch products using cursor-based pagination.
     *
     * @param int|null    $afterRank         Cursor position (rank value)
     * @param int         $pageSize          Items per page
     * @param string|null $productTypeFilter Optional product type filter (e.g., 'sneakers', 'apparel', null for all)
     *
     * @return array{result: PaginatedResultDto, lastRank: int|null}
     */
    public function fetchProductsWithCursor(
        ?int $afterRank = null,
        int $pageSize = 100,
        ?string $productTypeFilter = null,
    ): array {
        $this->logger->info('Fetching products from KicksDB with cursor', [
            'after_rank' => $afterRank,
            'page_size' => $pageSize,
            'product_type_filter' => $productTypeFilter,
            'market' => $this->market,
            'currency' => $this->currency,
        ]);

        $result = $this->apiClient->getStockXProductsWithCursor(
            afterRank: $afterRank,
            pageSize: $pageSize,
            productTypeFilter: $productTypeFilter,
            market: $this->market,
            currency: $this->currency,
        );

        $products = [];
        foreach ($result['products'] as $productData) {
            try {
                $products[] = ExternalProductDto::fromKicksDb($productData, $this->currency);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to parse product from KicksDB', [
                    'product_id' => $productData['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Fetched products from KicksDB with cursor', [
            'after_rank' => $afterRank,
            'fetched_count' => count($products),
            'total_count' => $result['totalCount'],
            'has_next_page' => $result['hasNextPage'],
            'last_rank' => $result['lastRank'],
        ]);

        $paginatedResult = new PaginatedResultDto(
            products: $products,
            totalCount: $result['totalCount'],
            pageNumber: 1,
            pageSize: $result['pageSize'],
            hasNextPage: $result['hasNextPage'],
            lastRank: $result['lastRank'],
        );

        return [
            'result' => $paginatedResult,
            'lastRank' => $result['lastRank'],
        ];
    }

    /**
     * Fetch all products using cursor-based pagination.
     * Returns a Generator that yields PaginatedResultDto for each batch.
     *
     * @param int         $batchSize         Products per batch (max 100)
     * @param string|null $productTypeFilter Optional product type filter (e.g., 'sneakers', 'apparel', null for all)
     *
     * @return \Generator<PaginatedResultDto>
     */
    public function fetchAllProducts(int $batchSize = 100, ?string $productTypeFilter = null): \Generator
    {
        $this->logger->info('Starting full product scan from KicksDB', [
            'batch_size' => $batchSize,
            'product_type_filter' => $productTypeFilter,
        ]);

        $afterRank = null;
        $batchNumber = 0;

        do {
            ++$batchNumber;

            $this->logger->debug('Fetching batch', [
                'batch_number' => $batchNumber,
                'after_rank' => $afterRank,
            ]);

            $response = $this->fetchProductsWithCursor(
                afterRank: $afterRank,
                pageSize: $batchSize,
                productTypeFilter: $productTypeFilter,
            );

            $result = $response['result'];
            $afterRank = $response['lastRank'];

            yield $result;
        } while ($result->hasNextPage && $afterRank !== null);

        $this->logger->info('Completed full product scan from KicksDB', [
            'total_batches' => $batchNumber,
        ]);
    }
}
