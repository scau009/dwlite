<?php

namespace App\Service\ProductSync\Provider;

use App\Service\ProductSync\Dto\ExternalProductDto;
use App\Service\ProductSync\Dto\PaginatedResultDto;
use App\Service\ProductSync\ProductDataProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * KicksDB implementation of the ProductDataProviderInterface.
 *
 * Provides access to StockX product data through the KicksDB API.
 */
class KicksDbProvider implements ProductDataProviderInterface
{
    public const PROVIDER_NAME = 'kicksdb';
    public const BASE_URL = 'https://stockx.com';

    public function __construct(
        private KicksDbApiClient $apiClient,
        private LoggerInterface $logger,
    ) {
    }

    public function getProviderName(): string
    {
        return self::PROVIDER_NAME;
    }

    public function fetchProducts(int $page, int $pageSize = 100): PaginatedResultDto
    {
        $this->logger->info('Fetching products from KicksDB', [
            'page' => $page,
            'page_size' => $pageSize,
        ]);

        $result = $this->apiClient->getStockXProducts($page, $pageSize);

        $products = [];
        foreach ($result['products'] as $productData) {
            try {
                $products[] = ExternalProductDto::fromKicksDb($productData);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to parse product from KicksDB', [
                    'product_id' => $productData['productId'] ?? 'unknown',
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

    public function fetchProduct(string $externalId): ?ExternalProductDto
    {
        $this->logger->info('Fetching single product from KicksDB', [
            'external_id' => $externalId,
        ]);

        $data = $this->apiClient->getStockXProduct($externalId);

        if ($data === null) {
            return null;
        }

        try {
            return ExternalProductDto::fromKicksDb($data);
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
        return 'USD';
    }

    public function getBaseUrl(): string
    {
        return self::BASE_URL;
    }
}
