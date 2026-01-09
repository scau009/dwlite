<?php

namespace App\Service\ProductSync\Provider;

use Psr\Log\LoggerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client wrapper for KicksDB API.
 *
 * Handles authentication, rate limiting, and HTTP communication
 * with the KicksDB API (v3).
 *
 * @see https://api.kicks.dev/v3/docs
 */
class KicksDbApiClient
{
    private const BASE_URL = 'https://api.kicks.dev/v3';

    // Supported markets
    public const MARKET_US = 'US';
    public const MARKET_UK = 'UK';
    public const MARKET_DE = 'DE';
    public const MARKET_FR = 'FR';
    public const MARKET_CH = 'CH';
    public const MARKET_CA = 'CA';
    public const MARKET_ES = 'ES';
    public const MARKET_IT = 'IT';
    public const MARKET_JP = 'JP';

    // Supported currencies
    public const CURRENCY_USD = 'USD';
    public const CURRENCY_GBP = 'GBP';
    public const CURRENCY_EUR = 'EUR';
    public const CURRENCY_CHF = 'CHF';
    public const CURRENCY_CAD = 'CAD';
    public const CURRENCY_JPY = 'JPY';

    // Sort options
    public const SORT_RANK = 'rank';
    public const SORT_RELEASE_DATE = 'release_date';

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
     * @param int         $pageNumber         Page number (1-based)
     * @param int         $pageSize           Items per page (max 100)
     * @param string|null $query              Search query (e.g., "air jordan 1")
     * @param string|null $filters            Filter expression (e.g., "brand = 'Nike'")
     * @param string      $sort               Sort order: 'rank' or 'release_date'
     * @param string      $market             Market code (e.g., 'US', 'UK')
     * @param string      $currency           Currency code (e.g., 'USD', 'EUR')
     * @param bool        $displayVariants    Include variants in response
     * @param bool        $displayPrices      Include prices in variants
     * @param bool        $displayIdentifiers Include identifiers (UPC/GTIN) in variants
     * @param bool        $displayStatistics  Include product statistics
     *
     * @return array{products: array, hasNextPage: bool, totalCount: int, pageNumber: int, pageSize: int}
     */
    public function getStockXProducts(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $query = null,
        ?string $filters = null,
        string $sort = self::SORT_RANK,
        string $market = self::MARKET_US,
        string $currency = self::CURRENCY_USD,
        bool $displayVariants = true,
        bool $displayPrices = true,
        bool $displayIdentifiers = true,
        bool $displayStatistics = false,
    ): array {
        $this->waitForRateLimit();

        $queryParams = [
            'page' => $pageNumber,
            'limit' => min($pageSize, 100),
            'sort' => $sort,
            'market' => $market,
            'currency' => $currency,
            'display[variants]' => $displayVariants ? 'true' : 'false',
            'display[prices]' => $displayPrices ? 'true' : 'false',
            'display[identifiers]' => $displayIdentifiers ? 'true' : 'false',
            'display[statistics]' => $displayStatistics ? 'true' : 'false',
        ];

        // Note: API docs state filters and query cannot be used together with pre-defined filters
        if ($query !== null) {
            $queryParams['query'] = $query;
        } elseif ($filters !== null) {
            $queryParams['filters'] = $filters;
        } else {
            // Default to sneakers filter when no search is provided
            $queryParams['filters'] = "product_type = 'sneakers'";
        }

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL.'/stockx/products', [
                'headers' => $this->getHeaders(),
                'query' => $queryParams,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $this->handleApiError($response, 'getStockXProducts');
            }

            $data = $response->toArray();
            $this->logger->debug('KicksDB API returned products', [
                'page' => $pageNumber,
                'count' => count($data['data'] ?? []),
                'total' => $data['meta']['total'] ?? 0,
            ]);

            $products = $data['data'] ?? [];
            $totalCount = $data['meta']['total'] ?? 0;

            return [
                'products' => $products,
                'hasNextPage' => count($products) >= $pageSize && ($pageNumber * $pageSize) < $totalCount,
                'totalCount' => $totalCount,
                'pageNumber' => $pageNumber,
                'pageSize' => $pageSize,
            ];
        } catch (HttpExceptionInterface $e) {
            $this->logger->error('KicksDB API HTTP error', [
                'error' => $e->getMessage(),
                'page' => $pageNumber,
            ]);
            throw new \RuntimeException('KicksDB API request failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Fetch a single product by ID.
     *
     * @param string $productId           The StockX product ID
     * @param string $market              Market code
     * @param string $currency            Currency code
     * @param bool   $displayVariants     Include variants
     * @param bool   $displayPrices       Include prices
     * @param bool   $displayIdentifiers  Include identifiers
     * @param bool   $displayStatistics   Include statistics
     */
    public function getStockXProduct(
        string $productId,
        string $market = self::MARKET_US,
        string $currency = self::CURRENCY_USD,
        bool $displayVariants = true,
        bool $displayPrices = true,
        bool $displayIdentifiers = true,
        bool $displayStatistics = false,
    ): ?array {
        $this->waitForRateLimit();

        $queryParams = [
            'market' => $market,
            'currency' => $currency,
            'display[variants]' => $displayVariants ? 'true' : 'false',
            'display[prices]' => $displayPrices ? 'true' : 'false',
            'display[identifiers]' => $displayIdentifiers ? 'true' : 'false',
            'display[statistics]' => $displayStatistics ? 'true' : 'false',
        ];

        try {
            $response = $this->httpClient->request('GET', self::BASE_URL.'/stockx/products/'.$productId, [
                'headers' => $this->getHeaders(),
                'query' => $queryParams,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 404) {
                $this->logger->info('Product not found in KicksDB', [
                    'product_id' => $productId,
                ]);

                return null;
            }

            if ($statusCode !== 200) {
                $this->handleApiError($response, 'getStockXProduct');
            }

            $data = $response->toArray();

            return $data['data'] ?? null;
        } catch (HttpExceptionInterface $e) {
            $this->logger->error('Failed to fetch product from KicksDB', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Search products by query string.
     *
     * @param string $searchQuery Search term (e.g., "air jordan 1 bred")
     * @param int    $pageNumber  Page number
     * @param int    $pageSize    Items per page
     * @param string $market      Market code
     * @param string $currency    Currency code
     *
     * @return array{products: array, hasNextPage: bool, totalCount: int, pageNumber: int, pageSize: int}
     */
    public function searchProducts(
        string $searchQuery,
        int $pageNumber = 1,
        int $pageSize = 100,
        string $market = self::MARKET_US,
        string $currency = self::CURRENCY_USD,
    ): array {
        return $this->getStockXProducts(
            pageNumber: $pageNumber,
            pageSize: $pageSize,
            query: $searchQuery,
            filters: null,
            market: $market,
            currency: $currency,
        );
    }

    /**
     * Get HTTP headers for API requests.
     */
    private function getHeaders(): array
    {
        return [
            'Authorization' => $this->apiKey,
            'Accept' => 'application/json',
        ];
    }

    /**
     * Handle API error responses.
     */
    private function handleApiError(\Symfony\Contracts\HttpClient\ResponseInterface $response, string $operation): void
    {
        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);
        $errorData = json_decode($content, true);

        $errorMessage = $errorData['detail'] ?? $errorData['title'] ?? "HTTP {$statusCode}";

        $this->logger->error('KicksDB API error', [
            'operation' => $operation,
            'status_code' => $statusCode,
            'error' => $errorMessage,
        ]);

        throw new \RuntimeException("KicksDB API error ({$operation}): {$errorMessage}");
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
            $waitSeconds = $retryAfter->getTimestamp() - time();
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
