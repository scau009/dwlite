<?php

namespace App\Service\ProductSync\Provider;

use Psr\Log\LoggerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client wrapper for KicksDB API.
 *
 * Handles authentication, rate limiting, and HTTP communication
 * with the KicksDB API.
 */
class KicksDbApiClient
{
    private const BASE_URL = 'https://api.kicks.dev/v3';

    public function __construct(
        private HttpClientInterface $httpClient,
        private RateLimiterFactory $kicksdbApiLimiter,
        private LoggerInterface $logger,
        private string $apiKey,
    ) {
    }

    /**
     * Fetch StockX products with pagination.
     *
     * @param int $pageNumber Page number (1-based)
     * @param int $pageSize Items per page (max 100)
     * @param array $filters Optional filters (brand, productType, etc.)
     *
     * @return array{products: array, hasNextPage: bool, totalCount: int, pageNumber: int, pageSize: int}
     */
    public function getStockXProducts(int $pageNumber = 1, int $pageSize = 100, array $filters = []): array
    {
        $this->waitForRateLimit();

        $query = array_merge([
            'page' => $pageNumber,
            'limit' => min($pageSize, 100), // Max 100 per page
            'display[variants]' => true,
            'display[prices]' => true,
        ], $filters);

        $response = $this->httpClient->request('GET', self::BASE_URL.'/stockx/products', [
            'headers' => [
                'Authorization' => $this->apiKey,
                'Accept' => 'application/json',
            ],
            'query' => $query,
        ]);
        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            $this->logger->error('KicksDB API error', [
                'status_code' => $statusCode,
                'page' => $pageNumber,
            ]);
            throw new \RuntimeException("KicksDB API returned status code: {$statusCode}");
        }

        $data = $response->toArray();
        $this->logger->debug('KicksDB API returned data', $data);

        return [
            'products' => $data['data'] ?? [],
            'hasNextPage' => count($data['data']) < $pageSize,
            'totalCount' => $data['meta']['total'] ?? 0,
            'pageNumber' => $pageNumber,
            'pageSize' => $pageSize,
        ];
    }

    /**
     * Fetch a single product by ID.
     *
     * @param string $productId The StockX product ID
     */
    public function getStockXProduct(string $productId): ?array
    {
        $this->waitForRateLimit();

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL.'/stockx/products/'.$productId, [
                'headers' => [
                    'Authorization' => $this->apiKey,
                    'Accept' => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() === 404) {
                return null;
            }

            return $response->toArray();
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch product from KicksDB', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Wait for rate limit if necessary.
     */
    private function waitForRateLimit(): void
    {
        $limiter = $this->kicksdbApiLimiter->create('kicksdb_api');
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter();
            $waitSeconds = $retryAfter ? $retryAfter->getTimestamp() - time() : 1;
            $waitSeconds = max(1, min($waitSeconds, 60)); // Clamp between 1-60 seconds

            $this->logger->info('Rate limit reached, waiting', [
                'retry_after' => $waitSeconds,
            ]);

            sleep($waitSeconds);

            // Retry after waiting
            $this->waitForRateLimit();
        }
    }
}
