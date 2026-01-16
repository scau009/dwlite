<?php

declare(strict_types=1);

namespace App\Message;

/**
 * 扫描失败的订单同步并重试的消息.
 *
 * 用于补偿扫描，找出同步失败的订单并重新触发同步。
 */
readonly class ScanFailedOrderSyncMessage
{
    public function __construct(
        public ?string $salesChannelId = null,
        public int $thresholdMinutes = 15,
        public int $maxRetryCount = 5,
        public int $limit = 100,
    ) {
    }

    public static function create(?string $salesChannelId = null): self
    {
        return new self(
            salesChannelId: $salesChannelId,
        );
    }

    /**
     * 获取阈值时间（只扫描此时间之前失败的订单，在执行时计算）.
     */
    public function getThreshold(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify(sprintf('-%d minutes', $this->thresholdMinutes));
    }
}
