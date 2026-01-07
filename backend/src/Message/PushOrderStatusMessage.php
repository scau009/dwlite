<?php

declare(strict_types=1);

namespace App\Message;

/**
 * 推送订单状态/物流信息到外部渠道的消息.
 */
readonly class PushOrderStatusMessage implements AsyncMessageInterface
{
    public const OP_CONFIRM = 'confirm';
    public const OP_SHIP = 'ship';
    public const OP_CANCEL = 'cancel';

    public function __construct(
        public string $orderId,
        public string $operation,
        public int $retryCount = 0,
    ) {
    }

    public static function confirm(string $orderId): self
    {
        return new self($orderId, self::OP_CONFIRM);
    }

    public static function ship(string $orderId): self
    {
        return new self($orderId, self::OP_SHIP);
    }

    public static function cancel(string $orderId): self
    {
        return new self($orderId, self::OP_CANCEL);
    }

    public function withRetry(int $currentRetryCount): self
    {
        return new self(
            $this->orderId,
            $this->operation,
            $currentRetryCount + 1,
        );
    }

    public function isConfirm(): bool
    {
        return $this->operation === self::OP_CONFIRM;
    }

    public function isShip(): bool
    {
        return $this->operation === self::OP_SHIP;
    }

    public function isCancel(): bool
    {
        return $this->operation === self::OP_CANCEL;
    }
}
