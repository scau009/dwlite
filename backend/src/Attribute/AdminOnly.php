<?php

namespace App\Attribute;

/**
 * 标记控制器或方法仅限管理员访问.
 *
 * 可用于类级别（整个控制器）或方法级别（单个接口）
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class AdminOnly
{
}
