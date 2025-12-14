<?php

namespace App\Service\Auth;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Service to manage JWT token blacklist using Redis cache.
 * Blacklisted tokens are stored until their original expiry time.
 */
class TokenBlacklistService
{
    private const BLACKLIST_PREFIX = 'jwt_blacklist_';

    public function __construct(
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Add a token to the blacklist.
     *
     * @param string $tokenId The JWT token ID (jti claim)
     * @param int $expiresAt Token expiration timestamp
     */
    public function blacklist(string $tokenId, int $expiresAt): void
    {
        $ttl = $expiresAt - time();

        if ($ttl <= 0) {
            return; // Token already expired, no need to blacklist
        }

        $item = $this->cache->getItem(self::BLACKLIST_PREFIX . $tokenId);
        $item->set(true);
        $item->expiresAfter($ttl);
        $this->cache->save($item);
    }

    /**
     * Check if a token is blacklisted.
     */
    public function isBlacklisted(string $tokenId): bool
    {
        $item = $this->cache->getItem(self::BLACKLIST_PREFIX . $tokenId);
        return $item->isHit() && $item->get() === true;
    }
}
