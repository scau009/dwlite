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
