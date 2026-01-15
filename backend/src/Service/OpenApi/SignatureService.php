<?php

namespace App\Service\OpenApi;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for signing and verifying API requests using HMAC-SHA256.
 */
class SignatureService
{
    // Time window for timestamp validation (5 minutes)
    private const TIMESTAMP_WINDOW = 300;

    // Nonce cache TTL (10 minutes)
    private const NONCE_TTL = 600;

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Build canonical string for signing.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $path Request path (e.g., /api/v1/open/warehouse/inbound/orders)
     * @param string $timestamp Unix timestamp as string
     * @param string $nonce Unique nonce (UUID)
     * @param string $bodyHash SHA256 hash of request body, or empty string
     */
    public function buildCanonicalString(
        string $method,
        string $path,
        string $timestamp,
        string $nonce,
        string $bodyHash
    ): string {
        return implode("\n", [
            strtoupper($method),
            $path,
            $timestamp,
            $nonce,
            $bodyHash,
        ]);
    }

    /**
     * Sign a canonical string with a secret.
     */
    public function sign(string $canonicalString, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $canonicalString, $secret);
    }

    /**
     * Verify a request signature.
     *
     * @param string $method HTTP method
     * @param string $path Request path
     * @param string $timestamp Unix timestamp as string
     * @param string $nonce Unique nonce
     * @param string $body Request body (raw)
     * @param string $providedSignature Signature from X-Signature header
     * @param string $secret API secret
     *
     * @return array{valid: bool, error?: string}
     */
    public function verifySignature(
        string $method,
        string $path,
        string $timestamp,
        string $nonce,
        string $body,
        string $providedSignature,
        string $secret
    ): array {
        // Validate timestamp
        if (!$this->isTimestampValid($timestamp)) {
            return ['valid' => false, 'error' => 'TIMESTAMP_EXPIRED'];
        }

        // Check nonce uniqueness
        if ($this->isNonceUsed($nonce)) {
            return ['valid' => false, 'error' => 'NONCE_REUSED'];
        }

        // Calculate body hash
        $bodyHash = $body !== '' ? hash('sha256', $body) : '';

        // Build canonical string
        $canonicalString = $this->buildCanonicalString(
            $method,
            $path,
            $timestamp,
            $nonce,
            $bodyHash
        );

        // Calculate expected signature
        $expectedSignature = $this->sign($canonicalString, $secret);

        // Constant-time comparison to prevent timing attacks
        if (!hash_equals($expectedSignature, $providedSignature)) {
            $this->logger->warning('Signature verification failed', [
                'method' => $method,
                'path' => $path,
                'nonce' => $nonce,
            ]);
            return ['valid' => false, 'error' => 'INVALID_SIGNATURE'];
        }

        // Mark nonce as used
        $this->markNonceAsUsed($nonce);

        return ['valid' => true];
    }

    /**
     * Validate timestamp (must be within 5 minutes window).
     */
    private function isTimestampValid(string $timestamp): bool
    {
        $requestTime = (int) $timestamp;
        $currentTime = time();
        $diff = abs($currentTime - $requestTime);

        return $diff <= self::TIMESTAMP_WINDOW;
    }

    /**
     * Check if a nonce has been used before.
     */
    private function isNonceUsed(string $nonce): bool
    {
        $cacheKey = $this->getNonceCacheKey($nonce);
        $item = $this->cache->getItem($cacheKey);

        return $item->isHit();
    }

    /**
     * Mark a nonce as used.
     */
    private function markNonceAsUsed(string $nonce): void
    {
        $cacheKey = $this->getNonceCacheKey($nonce);
        $item = $this->cache->getItem($cacheKey);
        $item->set(true);
        $item->expiresAfter(self::NONCE_TTL);
        $this->cache->save($item);
    }

    /**
     * Get cache key for nonce.
     */
    private function getNonceCacheKey(string $nonce): string
    {
        return 'openapi_nonce_' . $nonce;
    }

    /**
     * Validate signature headers presence.
     *
     * @param array<string, string> $headers
     *
     * @return array{valid: bool, error?: string}
     */
    public function validateHeaders(array $headers): array
    {
        $required = ['x-api-key', 'x-timestamp', 'x-nonce', 'x-signature'];

        foreach ($required as $header) {
            if (empty($headers[$header])) {
                return [
                    'valid' => false,
                    'error' => sprintf('Missing required header: %s', strtoupper($header)),
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Calculate body hash for a given body.
     */
    public function calculateBodyHash(string $body): string
    {
        return $body !== '' ? hash('sha256', $body) : '';
    }
}
