<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Service\Auth\TokenBlacklistService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class TokenBlacklistServiceTest extends TestCase
{
    private CacheItemPoolInterface&MockObject $cache;
    private TokenBlacklistService $service;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->service = new TokenBlacklistService($this->cache);
    }

    public function testBlacklistToken(): void
    {
        $cacheItem = $this->createMock(CacheItemInterface::class);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with($this->stringContains('token_blacklist_'))
            ->willReturn($cacheItem);

        $cacheItem->expects($this->once())
            ->method('set')
            ->with(true)
            ->willReturnSelf();

        $cacheItem->expects($this->once())
            ->method('expiresAfter')
            ->with(3600)
            ->willReturnSelf();

        $this->cache->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        $this->service->blacklist('test-token-id', 3600);
    }

    public function testIsBlacklistedTrue(): void
    {
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with($this->stringContains('token_blacklist_'))
            ->willReturn($cacheItem);

        $result = $this->service->isBlacklisted('blacklisted-token-id');

        $this->assertTrue($result);
    }

    public function testIsBlacklistedFalse(): void
    {
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with($this->stringContains('token_blacklist_'))
            ->willReturn($cacheItem);

        $result = $this->service->isBlacklisted('valid-token-id');

        $this->assertFalse($result);
    }
}
