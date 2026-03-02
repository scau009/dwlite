<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Provider\Poizon;

use App\Service\ChannelGateway\Exception\ChannelApiException;
use App\Service\ChannelGateway\Exception\ChannelAuthException;
use App\Service\ChannelGateway\Exception\ChannelRateLimitException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client wrapper for the Poizon (得物) Open API.
 *
 * Handles MD5-based request signing, authentication, and HTTP
 * communication with the Poizon Open Platform.
 */
class PoizonApiClient
{
    private const BASE_URL = 'https://open.poizon.com';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    // ========== Brand APIs ==========

    /**
     * Query brands by IDs.
     *
     * Used by testConnection() to verify credentials.
     *
     * @param int[] $brandIds
     */
    public function getBrandsByIds(string $appKey, string $appSecret, array $brandIds, string $language = 'en'): array
    {
        return $this->request($appKey, $appSecret, 'POST', '/dop/api/v1/pop/api/v1/intl-commodity/intl/brand/query/by-id', [
            'brandIds' => $brandIds,
            'language' => $language,
        ]);
    }

    // ========== Product/SKU APIs ==========

    /**
     * Query SKU & SPU information by brand official item number (article number).
     *
     * @return array<string, mixed>
     */
    public function querySkuInfoByArticleNumber(
        string $appKey,
        string $appSecret,
        string $articleNumber,
        string $region,
        ?bool $sellerStatusEnable = null,
        ?bool $buyStatusEnable = null,
        string $language = 'en',
    ): array {
        $params = [
            'articleNumber' => $articleNumber,
            'region' => $region,
            'language' => $language,
        ];

        if ($sellerStatusEnable !== null) {
            $params['sellerStatusEnable'] = $sellerStatusEnable;
        }

        if ($buyStatusEnable !== null) {
            $params['buyStatusEnable'] = $buyStatusEnable;
        }

        return $this->request($appKey, $appSecret, 'POST', '/dop/api/v1/pop/api/v1/intl-commodity/intl/sku/sku-basic-info/by-article-number', $params);
    }

    // ========== Listing APIs ==========

    /**
     * Create a new manual listing bid on Poizon.
     *
     * @return array<string, mixed>
     */
    public function manualListing(
        string $appKey,
        string $appSecret,
        string $requestId,
        int $globalSkuId,
        int $price,
        int $quantity,
        string $countryCode,
        string $deliveryCountryCode,
        string $currency,
        string $language = 'en',
        string $timeZone = 'Asia/Shanghai',
        ?string $sizeType = null,
        ?string $merchantSource = null,
    ): array {
        $params = [
            'requestId' => $requestId,
            'globalSkuId' => $globalSkuId,
            'price' => $price,
            'quantity' => $quantity,
            'countryCode' => $countryCode,
            'deliveryCountryCode' => $deliveryCountryCode,
            'currency' => $currency,
            'language' => $language,
            'timeZone' => $timeZone,
        ];

        if ($sizeType !== null) {
            $params['sizeType'] = $sizeType;
        }

        if ($merchantSource !== null) {
            $params['merchantSource'] = $merchantSource;
        }

        return $this->request($appKey, $appSecret, 'POST', '/dop/api/v1/pop/api/v1/submit-bid/normal-autonomous-bidding', $params);
    }

    /**
     * Update an existing manual listing bid on Poizon.
     *
     * @return array<string, mixed>
     */
    public function updateManualListing(
        string $appKey,
        string $appSecret,
        string $requestId,
        string $sellerBiddingNo,
        ?int $globalSkuId,
        int $price,
        int $quantity,
        int $oldQuantity,
        string $countryCode,
        string $deliveryCountryCode,
        string $currency,
        string $language = 'en',
        string $timeZone = 'Asia/Shanghai',
    ): array {
        $params = [
            'requestId' => $requestId,
            'sellerBiddingNo' => $sellerBiddingNo,
            'price' => $price,
            'quantity' => $quantity,
            'oldQuantity' => $oldQuantity,
            'countryCode' => $countryCode,
            'deliveryCountryCode' => $deliveryCountryCode,
            'currency' => $currency,
            'language' => $language,
            'timeZone' => $timeZone,
        ];

        if ($globalSkuId !== null) {
            $params['globalSkuId'] = $globalSkuId;
        }

        return $this->request($appKey, $appSecret, 'POST', '/dop/api/v1/pop/api/v1/update-bid/normal-autonomous-bidding', $params);
    }

    /**
     * Cancel an existing listing bid on Poizon.
     *
     * @return array<string, mixed>
     */
    public function cancelListing(
        string $appKey,
        string $appSecret,
        string $sellerBiddingNo,
        string $language = 'en',
        string $timeZone = 'Asia/Shanghai',
    ): array {
        return $this->request($appKey, $appSecret, 'POST', '/dop/api/v1/pop/api/v1/cancel-bid/cancel-bidding', [
            'sellerBiddingNo' => $sellerBiddingNo,
            'language' => $language,
            'timeZone' => $timeZone,
        ]);
    }

    // ========== Order APIs ==========

    /**
     * Query the Poizon order list.
     *
     * Endpoint: POST /dop/api/v1/pop/api/v2/order/generic_list
     * Time range: max 7 days (start_created / end_created). Defaults to last 7 days if omitted.
     *
     * @return array<string, mixed>
     */
    public function queryOrders(
        string $appKey,
        string $appSecret,
        ?string $orderNo = null,
        ?string $orderType = null,
        ?string $expressNo = null,
        ?int $orderStatus = null,
        ?string $startCreated = null,
        ?string $endCreated = null,
        ?int $skuId = null,
        ?int $spuId = null,
        ?string $warehouseCode = null,
        ?bool $orderByCreateTimeDesc = null,
        ?int $confirmOrderStatus = null,
        int $pageNo = 1,
        int $pageSize = 20,
        ?int $orderBySpu = null,
        string $language = 'en',
        string $timeZone = 'Asia/Shanghai',
    ): array {
        $params = [
            'page_no' => $pageNo,
            'page_size' => $pageSize,
            'language' => $language,
            'timeZone' => $timeZone,
        ];

        if ($orderNo !== null) {
            $params['order_no'] = $orderNo;
        }
        if ($orderType !== null) {
            $params['order_type'] = $orderType;
        }
        if ($expressNo !== null) {
            $params['express_no'] = $expressNo;
        }
        if ($orderStatus !== null) {
            $params['order_status'] = $orderStatus;
        }
        if ($startCreated !== null) {
            $params['start_created'] = $startCreated;
        }
        if ($endCreated !== null) {
            $params['end_created'] = $endCreated;
        }
        if ($skuId !== null) {
            $params['sku_id'] = $skuId;
        }
        if ($spuId !== null) {
            $params['spu_id'] = $spuId;
        }
        if ($warehouseCode !== null) {
            $params['warehouse_code'] = $warehouseCode;
        }
        if ($orderByCreateTimeDesc !== null) {
            $params['order_by_create_time_desc'] = $orderByCreateTimeDesc;
        }
        if ($confirmOrderStatus !== null) {
            $params['confirmOrderStatus'] = $confirmOrderStatus;
        }
        if ($orderBySpu !== null) {
            $params['order_by_spu'] = $orderBySpu;
        }

        return $this->request($appKey, $appSecret, 'POST', '/dop/api/v1/pop/api/v2/order/generic_list', $params);
    }

    /**
     * Confirm an order on Poizon.
     *
     * @return array<string, mixed>
     */
    public function confirmOrder(
        string $appKey,
        string $appSecret,
        string $orderItemNo,
        string $language = 'en',
        string $timeZone = 'Asia/Shanghai',
    ): array {
        return $this->request($appKey, $appSecret, 'POST', '/dop/api/v1/pop/api/v1/order/confirm', [
            'orderItemNo' => $orderItemNo,
            'language' => $language,
            'timeZone' => $timeZone,
        ]);
    }

    /**
     * Ship one or more orders on Poizon.
     *
     * @param string[] $orderNoList  Order number(s) to ship
     * @param int      $carrier      Carrier code (e.g. 7=UPS, 8=FedEx, 100=Self-delivery)
     * @param string   $deliveryRegion Seller's shipping origin (US, CN, HK, JP, KR …)
     * @param string   $deliveryType   OFFLINE_EXPRESS_DELIVERY | SELF_DELIVERY | ONLINE_EXPRESS_DELIVERY
     *
     * @return array<string, mixed>
     */
    public function shipOrder(
        string $appKey,
        string $appSecret,
        array $orderNoList,
        int $carrier,
        string $deliveryRegion,
        string $deliveryType,
        ?string $expressNo = null,
        string $language = 'en',
        string $timeZone = 'Asia/Shanghai',
    ): array {
        $params = [
            'order_no_list' => $orderNoList,
            'carrier' => $carrier,
            'delivery_region' => $deliveryRegion,
            'delivery_type' => $deliveryType,
            'language' => $language,
            'timeZone' => $timeZone,
        ];

        if ($expressNo !== null) {
            $params['express_no'] = $expressNo;
        }

        return $this->request($appKey, $appSecret, 'POST', '/dop/api/v1/pop/api/v1/order/delivery', $params);
    }

    // ========== Internal Methods ==========

    /**
     * Generate the Poizon request signature.
     *
     * Algorithm (per official spec):
     *   1. ksort() all params (excluding 'sign' itself)
     *   2. Skip params whose value is null or empty string
     *   3. Array values: JSON-encode, strip outer [], urlencode the result
     *   4. Scalar values: urlencode both key and value
     *   5. Concatenate as "k=v&k=v" then append appSecret (no trailing &)
     *   6. sign = strtoupper(md5(stringSignTemp))
     *
     * @param array<string, mixed> $params Params without 'sign' key
     */
    public function createSign(array $params, string $appSecret): string
    {
        // Remove sign key if it slipped in
        unset($params['sign']);

        ksort($params);

        $parts = [];
        foreach ($params as $key => $value) {
            // Skip empty values — they do not participate in signing
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                // Encode as JSON, then strip the outer [ ] brackets
                $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $value = substr((string) $encoded, 1, -1);
            }

            // Both key and value must be URL-encoded (UTF-8)
            $parts[] = urlencode((string) $key).'='.urlencode((string) $value);
        }

        $queryString = implode('&', $parts).$appSecret;

        return strtoupper(md5($queryString));
    }

    /**
     * Make a signed authenticated request to the Poizon Open API.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @throws ChannelAuthException
     * @throws ChannelRateLimitException
     * @throws ChannelApiException
     */
    public function request(string $appKey, string $appSecret, string $method, string $endpoint, array $params = []): array
    {
        $params['app_key'] = $appKey;
        $params['timestamp'] = (int) (microtime(true) * 1000);
        $sign = $this->createSign($params, $appSecret);
        $params['sign'] = $sign;

        $options = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];

        if ($method === 'GET') {
            // Array values are already JSON-encoded strings for signing;
            // send them the same way in query params.
            $query = [];
            foreach ($params as $key => $value) {
                $query[$key] = is_array($value)
                    ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : $value;
            }
            $options['query'] = $query;
        } else {
            $options['json'] = $params;
        }

        $this->logger->info('[POIZON] API request', [
            'method' => $method,
            'endpoint' => $endpoint,
            'app_key' => $appKey,
        ]);

        try {
            $response = $this->httpClient->request($method, self::BASE_URL.$endpoint, $options);
            $statusCode = $response->getStatusCode();
            $responseData = $response->toArray(false);

            $this->logger->info('[POIZON] API response', [
                'status_code' => $statusCode,
                'response' => $responseData,
            ]);

            if ($statusCode >= 400) {
                $this->handleHttpError(
                    $statusCode,
                    (string) ($responseData['code'] ?? 'UNKNOWN'),
                    $responseData['msg'] ?? $responseData['message'] ?? 'Unknown error'
                );
            }

            return $responseData;
        } catch (ChannelApiException|ChannelAuthException|ChannelRateLimitException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('[POIZON] API request failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            throw new ChannelApiException(sprintf('[POIZON] API error: %s', $e->getMessage()), 'REQUEST_FAILED', 500);
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
            throw new ChannelAuthException(sprintf('[POIZON] Authentication failed: %s', $errorMessage), $errorCode);
        }

        if ($statusCode === 429) {
            throw new ChannelRateLimitException(sprintf('[POIZON] Rate limit exceeded: %s', $errorMessage));
        }

        throw new ChannelApiException(sprintf('[POIZON] API error: %s', $errorMessage), $errorCode, $statusCode);
    }
}
