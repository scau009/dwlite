<?php

declare(strict_types=1);

namespace App\Message;

/**
 * 定时触发订单拉取调度的消息.
 *
 * 由 Scheduler 触发，扇出到各个活跃渠道的 PullOrdersMessage。
 */
readonly class ScheduleOrderPullMessage
{
    public function __construct(
        public \DateTimeImmutable $scheduledAt,
    ) {
    }

    public static function create(): self
    {
        return new self(
            scheduledAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }
}
