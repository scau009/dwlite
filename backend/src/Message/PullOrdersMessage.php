<?php

declare(strict_types=1);

namespace App\Message;

/**
 * 从外部渠道拉取订单的消息.
 *
 * 订单拉取始终在平台级别进行，API 凭证存储在 SalesChannel.config 中。
 */
readonly class PullOrdersMessage implements AsyncMessageInterface
{
    public function __construct(
        public string $salesChannelId,
        public \DateTimeImmutable $startTime,
        public \DateTimeImmutable $endTime,
        public int $page = 1,
        public int $pageSize = 100,
    ) {
    }

    /**
     * 创建定时拉取消息（拉取最近 N 分钟的订单）.
     */
    public static function createScheduled(
        string $salesChannelId,
        int $lookbackMinutes = 10,
    ): self {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return new self(
            salesChannelId: $salesChannelId,
            startTime: $now->modify(sprintf('-%d minutes', $lookbackMinutes)),
            endTime: $now,
        );
    }

    /**
     * 创建下一页消息.
     */
    public function nextPage(): self
    {
        return new self(
            salesChannelId: $this->salesChannelId,
            startTime: $this->startTime,
            endTime: $this->endTime,
            page: $this->page + 1,
            pageSize: $this->pageSize,
        );
    }

    public function hasNextPage(int $fetchedCount): bool
    {
        return $fetchedCount >= $this->pageSize;
    }
}
