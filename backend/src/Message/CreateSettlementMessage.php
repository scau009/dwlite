<?php

declare(strict_types=1);

namespace App\Message;

/**
 * 创建结算单消息 - 履约单完成后触发.
 */
readonly class CreateSettlementMessage implements AsyncMessageInterface
{
    public function __construct(
        public string $fulfillmentId,
    ) {
    }

    public static function create(string $fulfillmentId): self
    {
        return new self($fulfillmentId);
    }
}
