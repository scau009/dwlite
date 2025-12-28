<?php

use App\Kernel;

// 设置全局默认时区为 UTC，数据库存储统一使用 UTC 时间
// 前端根据用户本地时区显示
date_default_timezone_set('UTC');

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
