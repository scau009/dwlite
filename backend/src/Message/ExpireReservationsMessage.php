<?php

declare(strict_types=1);

namespace App\Message;

/**
 * 过期库存预留清理消息.
 */
readonly class ExpireReservationsMessage
{
    public function __construct(
        public int $limit = 100,
    ) {
    }

    /**
     * Create with default settings.
     */
    public static function create(int $limit = 100): self
    {
        return new self($limit);
    }

    /**
     * Get the current time (calculated at execution time).
     */
    public function getNow(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
