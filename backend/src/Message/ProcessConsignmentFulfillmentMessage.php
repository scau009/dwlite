<?php

declare(strict_types=1);

namespace App\Message;

/**
 * 处理寄售履约单消息.
 *
 * 当寄售履约单创建后，通过此消息触发自动化处理：
 * - 将履约单状态从 pending 转为 processing
 * - 创建仓库出库单
 */
class ProcessConsignmentFulfillmentMessage implements AsyncMessageInterface
{
    public function __construct(
        public readonly string $fulfillmentId,
    ) {
    }
}
