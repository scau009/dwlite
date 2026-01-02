<?php

namespace App\Service\RuleEngine;

use App\Entity\RuleExecutionLog;
use App\Repository\RuleExecutionLogRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * 规则引擎核心服务.
 *
 * 负责表达式的验证和执行
 */
class RuleEngineService
{
    private ExpressionLanguage $expressionLanguage;

    public function __construct(
        private readonly RuleExecutionLogRepository $logRepository,
        private readonly LoggerInterface $logger,
        private readonly bool $enableLogging = true,
    ) {
        $this->expressionLanguage = new ExpressionLanguage();
        $this->expressionLanguage->registerProvider(new RuleExpressionProvider());
    }

    /**
     * 验证表达式语法.
     *
     * @param array $allowedVariables 允许的变量名列表
     *
     * @return array{valid: bool, error: ?string}
     */
    public function validateExpression(string $expression, array $allowedVariables = []): array
    {
        try {
            // 使用 lint 方法验证语法
            $this->expressionLanguage->lint($expression, $allowedVariables);

            return ['valid' => true, 'error' => null];
        } catch (\Throwable $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 评估表达式.
     *
     * @throws \RuntimeException 当表达式执行失败时
     */
    public function evaluate(string $expression, array $context): mixed
    {
        try {
            return $this->expressionLanguage->evaluate($expression, $context);
        } catch (\Throwable $e) {
            $this->logger->error('Expression evaluation failed', [
                'expression' => $expression,
                'context_keys' => array_keys($context),
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Expression evaluation failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * 安全评估表达式（不抛出异常）.
     *
     * @return array{success: bool, result: mixed, error: ?string}
     */
    public function safeEvaluate(string $expression, array $context): array
    {
        try {
            $result = $this->expressionLanguage->evaluate($expression, $context);

            return ['success' => true, 'result' => $result, 'error' => null];
        } catch (\Throwable $e) {
            return ['success' => false, 'result' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * 执行规则链.
     *
     * @param array $rules 规则数组，每个规则包含:
     *                     - expression: 表达式
     *                     - conditionExpression: 条件表达式（可选）
     *                     - config: 配置（可选）
     *                     - ruleId: 规则ID（用于日志）
     *                     - ruleType: 规则类型（用于日志）
     */
    public function executeRuleChain(
        array $rules,
        array $context,
        mixed $initialValue,
        string $contextType,
        ?string $contextId = null,
    ): mixed {
        $value = $initialValue;

        foreach ($rules as $ruleData) {
            $expression = $ruleData['expression'];
            $conditionExpression = $ruleData['conditionExpression'] ?? null;
            $config = $ruleData['config'] ?? [];
            $ruleId = $ruleData['ruleId'] ?? 'unknown';
            $ruleType = $ruleData['ruleType'] ?? 'unknown';

            // 构建执行上下文
            $execContext = array_merge($context, [
                'value' => $value,
                'config' => $config,
            ]);

            // 检查条件（如果有）
            if ($conditionExpression) {
                $conditionResult = $this->safeEvaluate($conditionExpression, $execContext);
                if (!$conditionResult['success'] || !$conditionResult['result']) {
                    // 条件不满足，跳过此规则
                    continue;
                }
            }

            // 执行规则
            $startTime = microtime(true);
            $evalResult = $this->safeEvaluate($expression, $execContext);
            $executionTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($evalResult['success']) {
                $value = $evalResult['result'];

                // 记录成功日志
                if ($this->enableLogging) {
                    $this->logExecution(
                        $ruleType,
                        $ruleId,
                        $contextType,
                        $contextId,
                        $execContext,
                        (string) $value,
                        $executionTimeMs,
                        true
                    );
                }
            } else {
                // 记录失败日志
                $this->logger->warning('Rule execution failed', [
                    'rule_id' => $ruleId,
                    'rule_type' => $ruleType,
                    'expression' => $expression,
                    'error' => $evalResult['error'],
                ]);

                if ($this->enableLogging) {
                    $this->logExecution(
                        $ruleType,
                        $ruleId,
                        $contextType,
                        $contextId,
                        $execContext,
                        null,
                        $executionTimeMs,
                        false,
                        $evalResult['error']
                    );
                }
            }
        }

        return $value;
    }

    /**
     * 测试执行单个规则.
     *
     * @return array{success: bool, result: mixed, error: ?string, executionTimeMs: int}
     */
    public function testRule(
        string $expression,
        ?string $conditionExpression,
        array $context,
        mixed $initialValue = 0,
    ): array {
        $execContext = array_merge($context, ['value' => $initialValue]);

        // 检查条件
        if ($conditionExpression) {
            $conditionResult = $this->safeEvaluate($conditionExpression, $execContext);
            if (!$conditionResult['success']) {
                return [
                    'success' => false,
                    'result' => null,
                    'error' => 'Condition expression error: '.$conditionResult['error'],
                    'executionTimeMs' => 0,
                    'conditionMet' => false,
                ];
            }
            if (!$conditionResult['result']) {
                return [
                    'success' => true,
                    'result' => $initialValue,
                    'error' => null,
                    'executionTimeMs' => 0,
                    'conditionMet' => false,
                ];
            }
        }

        // 执行表达式
        $startTime = microtime(true);
        $result = $this->safeEvaluate($expression, $execContext);
        $executionTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        return [
            'success' => $result['success'],
            'result' => $result['result'],
            'error' => $result['error'],
            'executionTimeMs' => $executionTimeMs,
            'conditionMet' => true,
        ];
    }

    /**
     * 获取可用的上下文变量.
     *
     * @return array<string, array{type: string, description: string}>
     */
    public function getAvailableVariables(string $contextType): array
    {
        return match ($contextType) {
            'merchant_pricing' => [
                'value' => ['type' => 'float', 'description' => 'Current price (chain)'],
                'cost' => ['type' => 'float', 'description' => 'Cost price'],
                'referencePrice' => ['type' => 'float', 'description' => 'SKU reference price'],
                'channelCode' => ['type' => 'string', 'description' => 'Channel code'],
                'config' => ['type' => 'array', 'description' => 'Rule config'],
            ],
            'merchant_stock' => [
                'value' => ['type' => 'int', 'description' => 'Current stock (chain)'],
                'availableStock' => ['type' => 'int', 'description' => 'Available stock'],
                'channelCode' => ['type' => 'string', 'description' => 'Channel code'],
                'config' => ['type' => 'array', 'description' => 'Rule config'],
            ],
            'platform_pricing' => [
                'value' => ['type' => 'float', 'description' => 'Current price (chain)'],
                'merchantPrice' => ['type' => 'float', 'description' => 'Merchant price'],
                'cost' => ['type' => 'float', 'description' => 'Cost price'],
                'brand' => ['type' => 'string', 'description' => 'Brand name'],
                'brandSlug' => ['type' => 'string', 'description' => 'Brand slug'],
                'category' => ['type' => 'string', 'description' => 'Category name'],
                'categorySlug' => ['type' => 'string', 'description' => 'Category slug'],
                'merchantId' => ['type' => 'string', 'description' => 'Merchant ID'],
                'channelCode' => ['type' => 'string', 'description' => 'Channel code'],
                'config' => ['type' => 'array', 'description' => 'Rule config'],
            ],
            'platform_settlement' => [
                'value' => ['type' => 'float', 'description' => 'Current amount'],
                'orderAmount' => ['type' => 'float', 'description' => 'Order amount'],
                'merchantId' => ['type' => 'string', 'description' => 'Merchant ID'],
                'channelCode' => ['type' => 'string', 'description' => 'Channel code'],
                'config' => ['type' => 'array', 'description' => 'Rule config'],
            ],
            default => [],
        };
    }

    /**
     * 获取可用的函数列表.
     *
     * @return array<string, array{signature: string, description: string, example: string}>
     */
    public function getAvailableFunctions(): array
    {
        return [
            'markup' => [
                'signature' => 'markup(price, rate)',
                'description' => 'Apply markup to price',
                'example' => 'markup(100, 0.15) = 115',
            ],
            'discount' => [
                'signature' => 'discount(price, rate)',
                'description' => 'Apply discount to price',
                'example' => 'discount(100, 0.1) = 90',
            ],
            'addFee' => [
                'signature' => 'addFee(price, rate, fixed = 0)',
                'description' => 'Add fee (percentage + fixed)',
                'example' => 'addFee(100, 0.05, 2) = 107',
            ],
            'ratio' => [
                'signature' => 'ratio(value, rate)',
                'description' => 'Apply ratio (floor)',
                'example' => 'ratio(100, 0.8) = 80',
            ],
            'limit' => [
                'signature' => 'limit(value, max)',
                'description' => 'Limit to maximum value',
                'example' => 'limit(150, 100) = 100',
            ],
            'tieredRate' => [
                'signature' => 'tieredRate(value, tiers)',
                'description' => 'Get tiered rate',
                'example' => 'tieredRate(8000, [[10000, 0.03], [5000, 0.04], [0, 0.05]]) = 0.04',
            ],
            'round' => [
                'signature' => 'round(value, precision = 2)',
                'description' => 'Round to precision',
                'example' => 'round(3.1415, 2) = 3.14',
            ],
            'floor' => [
                'signature' => 'floor(value)',
                'description' => 'Round down',
                'example' => 'floor(3.9) = 3',
            ],
            'ceil' => [
                'signature' => 'ceil(value)',
                'description' => 'Round up',
                'example' => 'ceil(3.1) = 4',
            ],
            'min' => [
                'signature' => 'min(a, b)',
                'description' => 'Get minimum value',
                'example' => 'min(5, 3) = 3',
            ],
            'max' => [
                'signature' => 'max(a, b)',
                'description' => 'Get maximum value',
                'example' => 'max(5, 3) = 5',
            ],
            'abs' => [
                'signature' => 'abs(value)',
                'description' => 'Get absolute value',
                'example' => 'abs(-5) = 5',
            ],
            'inList' => [
                'signature' => 'inList(value, list)',
                'description' => 'Check if value in list',
                'example' => 'inList("nike", ["nike", "adidas"]) = true',
            ],
            'startsWith' => [
                'signature' => 'startsWith(str, prefix)',
                'description' => 'Check string prefix',
                'example' => 'startsWith("nike-air", "nike") = true',
            ],
            'endsWith' => [
                'signature' => 'endsWith(str, suffix)',
                'description' => 'Check string suffix',
                'example' => 'endsWith("nike-air", "air") = true',
            ],
            'contains' => [
                'signature' => 'contains(str, needle)',
                'description' => 'Check string contains',
                'example' => 'contains("nike-air-max", "air") = true',
            ],
            'config' => [
                'signature' => 'config(key, default = null)',
                'description' => 'Get config value',
                'example' => 'config("rate", 0.1)',
            ],
        ];
    }

    /**
     * 记录执行日志.
     */
    private function logExecution(
        string $ruleType,
        string $ruleId,
        string $contextType,
        ?string $contextId,
        array $inputData,
        ?string $outputValue,
        int $executionTimeMs,
        bool $success,
        ?string $errorMessage = null,
    ): void {
        try {
            // 简化 inputData，移除 config 中的敏感信息
            $sanitizedInput = $inputData;
            unset($sanitizedInput['config']);

            $log = $success
                ? RuleExecutionLog::createSuccess(
                    $ruleType,
                    $ruleId,
                    $contextType,
                    $contextId,
                    $sanitizedInput,
                    $outputValue ?? '',
                    $executionTimeMs
                )
                : RuleExecutionLog::createFailure(
                    $ruleType,
                    $ruleId,
                    $contextType,
                    $contextId,
                    $sanitizedInput,
                    $executionTimeMs,
                    $errorMessage ?? 'Unknown error'
                );

            $this->logRepository->save($log, true);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to save rule execution log', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
