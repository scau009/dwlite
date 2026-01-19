<?php

declare(strict_types=1);

namespace App\Message;

/**
 * 过期库存预留清理消息.
 */
class ExpireReservationsMessage
{
    public function __construct(
        public readonly \DateTimeImmutable $scheduledAt,
        public readonly int $limit = 100,
    ) {
    }
}
