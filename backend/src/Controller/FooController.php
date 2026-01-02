<?php

namespace App\Controller;

use App\Service\MetricsService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class FooController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private MetricsService $metrics,
    ) {
    }

    #[Route('/foo', name: 'foo', methods: ['GET'])]
    public function index(): JsonResponse
    {
        // ==================== Counter 示例 ====================
        // 统计业务事件：foo 接口被访问次数
        $this->metrics->counter(
            'foo_visits_total',           // 指标名称
            'Total visits to foo endpoint', // 说明
            ['source' => 'web']            // 标签（可选）
        );

        $this->logger->info('Foo endpoint accessed', [
            'action' => 'foo_access',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);

        // ==================== Histogram 示例 ====================
        // 模拟一个业务操作并记录其耗时
        $startTime = microtime(true);
        $randomValue = $this->simulateBusinessLogic();
        $duration = microtime(true) - $startTime;

        $this->metrics->histogram(
            'foo_business_operation_seconds',  // 指标名称
            'Duration of business operation',   // 说明
            $duration,                          // 观察值
            ['operation' => 'random_calc']      // 标签
        );

        // ==================== Gauge 示例 ====================
        // 记录当前随机值（演示用，实际可以是队列长度、连接数等）
        $this->metrics->gauge(
            'foo_current_random_value',
            'Current random value from foo endpoint',
            $randomValue,
            ['type' => 'demo']
        );

        $data = [
            'message' => 'Hello from Foo!',
            'timestamp' => time(),
            'random' => $randomValue,
            'operation_time_ms' => round($duration * 1000, 2),
        ];

        $this->logger->debug('Returning foo response', ['data' => $data]);

        return new JsonResponse($data);
    }

    #[Route('/foo/error', name: 'foo_error', methods: ['GET'])]
    public function error(): JsonResponse
    {
        // 统计错误事件
        $this->metrics->counter(
            'foo_errors_total',
            'Total errors in foo endpoint',
            ['error_type' => 'simulated', 'severity' => 'error']
        );

        $this->logger->warning('Simulated warning triggered');
        $this->logger->error('Simulated error for testing', [
            'error_code' => 'E001',
            'details' => 'This is a test error',
        ]);

        return new JsonResponse([
            'message' => 'Error logged for testing',
            'level' => 'error',
        ]);
    }

    #[Route('/foo/slow', name: 'foo_slow', methods: ['GET'])]
    public function slow(): JsonResponse
    {
        // 模拟慢接口，用于测试 P95
        $delay = random_int(100, 500) / 1000; // 100-500ms
        usleep((int) ($delay * 1000000));

        $this->metrics->histogram(
            'foo_slow_request_seconds',
            'Duration of slow requests',
            $delay,
            ['endpoint' => 'slow']
        );

        return new JsonResponse([
            'message' => 'Slow response',
            'delay_ms' => round($delay * 1000, 2),
        ]);
    }

    private function simulateBusinessLogic(): int
    {
        // 模拟一些业务逻辑处理
        usleep(random_int(1000, 5000)); // 1-5ms

        return random_int(1, 100);
    }
}
