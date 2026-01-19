<?php

namespace App\Service\OpenApi;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for handling idempotency in Open API requests.
 */
class IdempotencyService
{
    // Idempotency key cache TTL (24 hours)
    private const CACHE_TTL = 86400;

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Check if an idempotency key has been used before.
     *
     * @return array{exists: bool, response?: array<string, mixed>}
     */
    public function checkIdempotencyKey(string $key): array
    {
        $cacheKey = $this->getCacheKey($key);
        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            $this->logger->debug('Idempotency key found in cache', ['key' => $key]);

            return [
                'exists' => true,
                'response' => $item->get(),
            ];
        }

        return ['exists' => false];
    }

    /**
     * Store a response for an idempotency key.
     *
     * @param array<string, mixed> $response
     */
    public function storeIdempotencyKey(string $key, array $response): void
    {
        $cacheKey = $this->getCacheKey($key);
        $item = $this->cache->getItem($cacheKey);
        $item->set($response);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        $this->logger->debug('Stored idempotency key', ['key' => $key]);
    }

    /**
     * Invalidate an idempotency key.
     */
    public function invalidateIdempotencyKey(string $key): void
    {
        $cacheKey = $this->getCacheKey($key);
        $this->cache->deleteItem($cacheKey);

        $this->logger->debug('Invalidated idempotency key', ['key' => $key]);
    }

    /**
     * Get cache key for idempotency.
     */
    private function getCacheKey(string $key): string
    {
        return 'openapi_idempotency_'.hash('sha256', $key);
    }

    /**
     * Validate idempotency key format.
     */
    public function validateKey(string $key): bool
    {
        // Key should be at least 16 characters and max 255 characters
        $length = strlen($key);

        return $length >= 16 && $length <= 255;
    }
}
