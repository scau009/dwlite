<?php

namespace App\Service;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\APC;

class MetricsService
{
    private CollectorRegistry $registry;
    private string $namespace = 'dwlite';

    public function __construct()
    {
        $adapter = new APC();
        $this->registry = new CollectorRegistry($adapter);
    }

    /**
     * Counter - 只能增加的计数器，适合统计累计事件
     * 例如：请求总数、订单数、错误数.
     */
    public function counter(string $name, string $help, array $labels = [], float $value = 1): void
    {
        $counter = $this->registry->getOrRegisterCounter(
            $this->namespace,
            $name,
            $help,
            array_keys($labels)
        );
        $counter->incBy($value, array_values($labels));
    }

    /**
     * Gauge - 可增可减的数值，适合统计当前状态
     * 例如：当前在线用户数、队列长度、内存使用.
     */
    public function gauge(string $name, string $help, float $value, array $labels = []): void
    {
        $gauge = $this->registry->getOrRegisterGauge(
            $this->namespace,
            $name,
            $help,
            array_keys($labels)
        );
        $gauge->set($value, array_values($labels));
    }

    /**
     * Histogram - 观察值的分布，适合统计延迟、大小等
     * 例如：响应时间、请求大小.
     */
    public function histogram(
        string $name,
        string $help,
        float $value,
        array $labels = [],
        array $buckets = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10]
    ): void {
        $histogram = $this->registry->getOrRegisterHistogram(
            $this->namespace,
            $name,
            $help,
            array_keys($labels),
            $buckets
        );
        $histogram->observe($value, array_values($labels));
    }

    /**
     * 记录 HTTP 请求持续时间（内置方法）.
     */
    public function recordRequestDuration(string $method, string $route, int $statusCode, float $duration): void
    {
        $this->histogram(
            'http_request_duration_seconds',
            'HTTP request duration in seconds',
            $duration,
            ['method' => $method, 'route' => $route, 'status_code' => (string) $statusCode]
        );
    }

    /**
     * 增加 HTTP 请求计数（内置方法）.
     */
    public function incrementRequestCount(string $method, string $route, int $statusCode): void
    {
        $this->counter(
            'http_requests_total',
            'Total number of HTTP requests',
            ['method' => $method, 'route' => $route, 'status_code' => (string) $statusCode]
        );
    }

    /**
     * 渲染 Prometheus 格式的指标.
     */
    public function render(): string
    {
        $renderer = new RenderTextFormat();

        return $renderer->render($this->registry->getMetricFamilySamples());
    }
}
