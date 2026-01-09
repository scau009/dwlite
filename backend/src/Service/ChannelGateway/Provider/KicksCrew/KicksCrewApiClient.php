<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Provider\KicksCrew;

use App\Service\ChannelGateway\Exception\ChannelApiException;
use App\Service\ChannelGateway\Exception\ChannelAuthException;
use App\Service\ChannelGateway\Exception\ChannelRateLimitException;
use Psr\Log\LoggerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client wrapper for Kicks Crew Seller API.
 *
 * Handles authentication via x-api-key header, rate limiting,
 * and HTTP communication with the CrewSupply API.
 */
class KicksCrewApiClient
{
    private const BASE_URL = 'https://api.crewsupply.kickscrew.com';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly RateLimiterFactory $kickscrewApiLimiter,
        private readonly LoggerInterface $logger,
    ) {
    }

    // ========== Product APIs ==========

    /**
     * Enquire product information by model number.
     *
     * @return array{code: int, data?: array{model_number: string, title: string, image: string, brand: string, category: string, gender: string, primary_size_code: string, sizes: array}}
     */
    public function getProduct(string $apiKey, string $modelNumber): array
    {
        return $this->request('GET', "/sapi/v2/product/{$modelNumber}", [], $apiKey);
    }

    /**
     * Get newly published products in the specified period.
     *
     * @param string $period period like "1d", "7d", etc
     */
    public function getNewProducts(string $apiKey, string $period): array
    {
        return $this->request('GET', "/sapi/v2/product/new/{$period}", [], $apiKey);
    }

    /**
     * Get platform lowest prices for given model numbers.
     *
     * @param string[] $modelNos Array of model numbers
     */
    public function getPlatformLowest(string $apiKey, array $modelNos): array
    {
        return $this->request('POST', '/sapi/v2/bidding/platform-lowest', [
            'model_nos' => $modelNos,
        ], $apiKey);
    }

    // ========== Listing APIs ==========

    /**
     * Create or update a single listing.
     *
     * @param array{model_no?: string, size_system?: string, size?: string, qty: int, price: int, brand?: string, ext_ref?: string, sku?: string, check_pending_orders?: bool} $data
     */
    public function updateStock(string $apiKey, array $data): array
    {
        return $this->request('POST', '/sapi/v2/stock/update', $data, $apiKey);
    }

    /**
     * Batch create or update multiple listings (max 1000 per request).
     *
     * @param array[] $items Array of listing items
     * @param bool $skipPriceUpdate Whether to skip price update
     */
    public function batchUpdateStock(string $apiKey, array $items, bool $skipPriceUpdate = false): array
    {
        return $this->request('POST', '/sapi/v2/stock/batch-update', [
            'items' => $items,
            'skipPriceUpdate' => $skipPriceUpdate,
        ], $apiKey);
    }

    /**
     * Get listings for a specific model number.
     */
    public function getListings(string $apiKey, string $modelNo): array
    {
        return $this->request('POST', '/sapi/v2/stock/get', [
            'model_no' => $modelNo,
        ], $apiKey);
    }

    /**
     * Get listing by external reference.
     */
    public function getListingByExtRef(string $apiKey, string $extRef): array
    {
        return $this->request('GET', "/sapi/v2/stock/{$extRef}", [], $apiKey);
    }

    /**
     * List all active listings with pagination.
     *
     * @param int $pageNo Page number (starts from 0)
     * @param int $pageSize Page size (max 1000, default 20)
     * @param int $minQty Minimum quantity to filter (default 1)
     */
    public function listAllListings(string $apiKey, int $pageNo = 0, int $pageSize = 100, int $minQty = 1): array
    {
        return $this->request('POST', '/sapi/v2/stock/list', [
            'page_no' => $pageNo,
            'page_size' => min($pageSize, 1000),
            'min_qty' => $minQty,
        ], $apiKey);
    }

    /**
     * Delete listings by items or external references.
     *
     * @param array{items?: array[], ext_refs?: string[], modified_before?: string} $data
     */
    public function deleteListings(string $apiKey, array $data): array
    {
        return $this->request('DELETE', '/sapi/v2/stock', $data, $apiKey);
    }

    /**
     * Delete all listings (dangerous!).
     */
    public function deleteAllListings(string $apiKey): array
    {
        return $this->request('DELETE', '/sapi/v2/stock/all', [], $apiKey);
    }

    // ========== Order APIs ==========

    /**
     * Get orders with pagination and filters.
     *
     * @param int $page Page number (starts from 1), 100 records per page
     * @param int|null $dateFrom Start date in UNIX timestamp (milliseconds)
     * @param int|null $dateTo End date in UNIX timestamp (milliseconds)
     * @param string|null $status Comma-separated status values
     */
    public function getOrders(string $apiKey, int $page = 1, ?int $dateFrom = null, ?int $dateTo = null, ?string $status = null): array
    {
        $params = ['page' => $page];

        if ($dateFrom !== null) {
            $params['date_from'] = $dateFrom;
        }
        if ($dateTo !== null) {
            $params['date_to'] = $dateTo;
        }
        if ($status !== null) {
            $params['status'] = $status;
        }

        return $this->request('GET', '/sapi/v2/orders', $params, $apiKey);
    }

    /**
     * Get a single order by ID.
     */
    public function getOrder(string $apiKey, int $orderId): array
    {
        return $this->request('GET', "/sapi/v2/order/{$orderId}", [], $apiKey);
    }

    /**
     * Cancel an order (declare out of stock).
     */
    public function outStockOrder(string $apiKey, string $orderId): array
    {
        return $this->request('POST', '/sapi/v2/order/out-stock', [
            'order_id' => $orderId,
        ], $apiKey);
    }

    /**
     * Mark order as packed (pending courier pickup).
     */
    public function packOrder(string $apiKey, string $orderId, ?string $courierSlug = null, ?string $trackNo = null): array
    {
        $data = ['order_id' => $orderId];

        if ($courierSlug !== null) {
            $data['courier_slug'] = $courierSlug;
        }
        if ($trackNo !== null) {
            $data['track_no'] = $trackNo;
        }

        return $this->request('POST', '/sapi/v2/order/pack', $data, $apiKey);
    }

    /**
     * Confirm order is shipped to KC warehouse.
     */
    public function confirmShipped(string $apiKey, string $orderId, ?string $courierSlug = null, ?string $trackNo = null): array
    {
        $data = ['order_id' => $orderId];

        if ($courierSlug !== null) {
            $data['courier_slug'] = $courierSlug;
        }
        if ($trackNo !== null) {
            $data['track_no'] = $trackNo;
        }

        return $this->request('POST', '/sapi/v2/order/confirm-shipped', $data, $apiKey);
    }

    /**
     * Download QR label for an order (returns PDF content).
     */
    public function getOrderLabel(string $apiKey, int $orderId): string
    {
        $this->waitForRateLimit();

        $response = $this->httpClient->request('GET', self::BASE_URL."/sapi/v2/order/label/{$orderId}", [
            'headers' => [
                'x-api-key' => $apiKey,
                'Accept' => 'application/pdf',
            ],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            $this->handleHttpError($statusCode, 'LABEL_ERROR', 'Failed to download order label');
        }

        return $response->getContent();
    }

    // ========== Internal Methods ==========

    /**
     * Make an authenticated request to the KC API.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     *
     * @throws ChannelAuthException
     * @throws ChannelRateLimitException
     * @throws ChannelApiException
     */
    private function request(string $method, string $endpoint, array $data, string $apiKey): array
    {
        $this->waitForRateLimit();

        $options = [
            'headers' => [
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];

        if ($method === 'GET') {
            $options['query'] = $data;
        } elseif (!empty($data)) {
            $options['json'] = $data;
        }

        $this->logger->debug('[KICKSCREW] API request', [
            'method' => $method,
            'endpoint' => $endpoint,
            'data' => $data,
        ]);

        try {
            $response = $this->httpClient->request($method, self::BASE_URL.$endpoint, $options);
            $statusCode = $response->getStatusCode();
            $responseData = $response->toArray(false);

            $this->logger->debug('[KICKSCREW] API response', [
                'status_code' => $statusCode,
                'response' => $responseData,
            ]);

            if ($statusCode >= 400) {
                $this->handleHttpError(
                    $statusCode,
                    (string) ($responseData['code'] ?? 'UNKNOWN'),
                    $responseData['message'] ?? 'Unknown error'
                );
            }

            return $responseData;
        } catch (ChannelApiException|ChannelAuthException|ChannelRateLimitException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('[KICKSCREW] API request failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            throw new ChannelApiException(sprintf('[KICKSCREW] API error: %s', $e->getMessage()), 'REQUEST_FAILED', 500);
        }
    }

    /**
     * Handle HTTP error responses.
     *
     * @throws ChannelAuthException
     * @throws ChannelRateLimitException
     * @throws ChannelApiException
     */
    private function handleHttpError(int $statusCode, string $errorCode, string $errorMessage): never
    {
        if ($statusCode === 401 || $statusCode === 403) {
            throw new ChannelAuthException(sprintf('[KICKSCREW] Authentication failed: %s', $errorMessage), $errorCode);
        }

        if ($statusCode === 429) {
            throw new ChannelRateLimitException(sprintf('[KICKSCREW] Rate limit exceeded: %s', $errorMessage));
        }

        throw new ChannelApiException(sprintf('[KICKSCREW] API error: %s', $errorMessage), $errorCode, $statusCode);
    }

    /**
     * Wait for rate limit if necessary.
     */
    private function waitForRateLimit(): void
    {
        $limiter = $this->kickscrewApiLimiter->create('kickscrew_api');
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter();
            $waitSeconds = $retryAfter->getTimestamp() - time();
            $waitSeconds = max(1, min($waitSeconds, 60));

            $this->logger->info('[KICKSCREW] Rate limit reached, waiting', [
                'retry_after' => $waitSeconds,
            ]);

            sleep($waitSeconds);

            // Retry after waiting
            $this->waitForRateLimit();
        }
    }
}
