<?php

declare(strict_types=1);

namespace App\Message;

/**
 * 订单分配消息 - 触发订单履约分配流程.
 */
class AllocateOrderMessage implements AsyncMessageInterface
{
    /**
     * @param string   $orderId            订单ID
     * @param string[] $excludedMerchantIds 已排除的商户ID列表
     * @param int      $attemptNumber      当前尝试次数
     */
    public function __construct(
        public readonly string $orderId,
        public readonly array $excludedMerchantIds = [],
        public readonly int $attemptNumber = 1,
    ) {
    }
}
