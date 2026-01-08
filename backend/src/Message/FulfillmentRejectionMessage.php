<?php

declare(strict_types=1);

namespace App\Message;

/**
 * 履约拒绝消息 - 触发履约单拒绝处理和重新分配.
 */
class FulfillmentRejectionMessage implements AsyncMessageInterface
{
    /**
     * @param string      $fulfillmentId 履约单ID
     * @param string      $reason        拒绝原因
     * @param string|null $rejectedBy    拒绝人ID（商户手动拒绝时）
     * @param bool        $isTimeout     是否为超时自动拒绝
     */
    public function __construct(
        public readonly string $fulfillmentId,
        public readonly string $reason,
        public readonly ?string $rejectedBy = null,
        public readonly bool $isTimeout = false,
    ) {
    }
}
