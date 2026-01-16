<?php

declare(strict_types=1);

namespace App\Message;

/**
 * 扫描待结算单消息 - 定时任务触发.
 */
readonly class ScanPendingSettlementsMessage implements AsyncMessageInterface
{
    public function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }
}
