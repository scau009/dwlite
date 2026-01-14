<?php

declare(strict_types=1);

namespace App\Message;

/**
 * 处理超时履约单消息 - 定时任务触发，检查并处理超时的履约单.
 */
class HandleExpiredFulfillmentsMessage implements AsyncMessageInterface
{
    public function __construct(
        public readonly \DateTimeImmutable $scheduledAt,
    ) {
    }

    public static function create(): self
    {
        return new self(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
    }
}
