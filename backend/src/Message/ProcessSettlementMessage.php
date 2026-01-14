<?php

declare(strict_types=1);

namespace App\Message;

/**
 * 处理结算入账消息 - T+N 到期后触发.
 */
readonly class ProcessSettlementMessage implements AsyncMessageInterface
{
    public function __construct(
        public string $settlementId,
    ) {}

    public static function create(string $settlementId): self
    {
        return new self($settlementId);
    }
}
