<?php

namespace App\Attribute;

use Attribute;

/**
 * 标记控制器或方法仅限仓库操作员访问
 *
 * 可用于类级别（整个控制器）或方法级别（单个接口）
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class WarehouseOnly
{
}
