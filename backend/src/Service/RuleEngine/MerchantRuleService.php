<?php

namespace App\Service\RuleEngine;

use App\Entity\MerchantInventory;
use App\Entity\MerchantRule;
use App\Entity\MerchantRuleAssignment;
use App\Entity\MerchantSalesChannel;
use App\Entity\RuleExecutionLog;
use App\Repository\MerchantRuleAssignmentRepository;
use App\Repository\MerchantRuleRepository;

/**
 * 商户规则服务.
 *
 * 处理商户配置的定价规则和库存分配规则
 */
class MerchantRuleService
{
    public function __construct(
        private readonly RuleEngineService $ruleEngine,
        private readonly MerchantRuleRepository $ruleRepository,
        private readonly MerchantRuleAssignmentRepository $assignmentRepository,
    ) {
    }

    /**
     * 计算商户定价.
     *
     * 根据商户配置的定价规则计算报价
     *
     * @return string 计算后的价格
     */
    public function calculatePrice(
        MerchantSalesChannel $merchantChannel,
        MerchantInventory $inventory,
        float $baseCost,
    ): string {
        $salesChannel = $merchantChannel->getSalesChannel();
        $sku = $inventory->getProductSku();

        // 构建上下文
        $context = [
            'cost' => $baseCost,
            'referencePrice' => (float) $sku->getPrice(),
            'originalPrice' => (float) ($sku->getOriginalPrice() ?? $sku->getPrice()),
            'channelCode' => $salesChannel->getCode(),
            'sizeValue' => $sku->getSizeValue(),
            'sizeUnit' => $sku->getSizeUnit()?->value,
        ];

        // 获取适用的定价规则
        $assignments = $this->assignmentRepository->findActiveByMerchantSalesChannelAndType(
            $merchantChannel,
            MerchantRule::TYPE_PRICING
        );

        if (empty($assignments)) {
            // 没有配置规则，返回成本价
            return bcmul((string) $baseCost, '1', 2);
        }

        // 转换为规则数组
        $rules = $this->convertAssignmentsToRules($assignments);

        // 执行规则链
        $result = $this->ruleEngine->executeRuleChain(
            $rules,
            $context,
            $baseCost,
            RuleExecutionLog::CONTEXT_PRICING,
            $inventory->getId()
        );

        // 确保价格有效
        return bcmul((string) max(0, $result), '1', 2);
    }

    /**
     * 计算库存分配.
     *
     * 根据商户配置的库存分配规则计算分配到渠道的库存数量
     *
     * @return int 分配的库存数量
     */
    public function calculateStockAllocation(
        MerchantSalesChannel $merchantChannel,
        MerchantInventory $inventory,
    ): int {
        $salesChannel = $merchantChannel->getSalesChannel();
        $availableStock = $inventory->getShareableQuantity();

        // 构建上下文
        $context = [
            'availableStock' => $availableStock,
            'totalStock' => $inventory->getTotalOnHand(),
            'reservedStock' => $inventory->getQuantityReserved(),
            'channelCode' => $salesChannel->getCode(),
        ];

        // 获取适用的库存分配规则
        $assignments = $this->assignmentRepository->findActiveByMerchantSalesChannelAndType(
            $merchantChannel,
            MerchantRule::TYPE_STOCK_ALLOCATION
        );

        if (empty($assignments)) {
            // 没有配置规则，返回全部可用库存
            return $availableStock;
        }

        // 转换为规则数组
        $rules = $this->convertAssignmentsToRules($assignments);

        // 执行规则链
        $result = $this->ruleEngine->executeRuleChain(
            $rules,
            $context,
            $availableStock,
            RuleExecutionLog::CONTEXT_STOCK_ALLOCATION,
            $inventory->getId()
        );

        // 确保库存有效（不能超过可用库存，不能为负）
        return max(0, min((int) $result, $availableStock));
    }

    /**
     * 获取商户的规则列表.
     *
     * @return array{data: MerchantRule[], total: int}
     */
    public function getRulesByMerchant(
        string $merchantId,
        int $page = 1,
        int $limit = 20,
        ?string $type = null,
        ?string $search = null,
    ): array {
        $merchant = $this->ruleRepository->getEntityManager()
            ->getReference('App\\Entity\\Merchant', $merchantId);

        return $this->ruleRepository->findByMerchantPaginated(
            $merchant,
            $page,
            $limit,
            $type,
            $search
        );
    }

    /**
     * 获取规则的分配列表.
     *
     * @return MerchantRuleAssignment[]
     */
    public function getRuleAssignments(MerchantRule $rule): array
    {
        return $this->assignmentRepository->findByRule($rule);
    }

    /**
     * 验证规则表达式.
     *
     * @return array{valid: bool, error: ?string}
     */
    public function validateExpression(string $expression, string $type): array
    {
        $contextType = $type === MerchantRule::TYPE_PRICING
            ? 'merchant_pricing'
            : 'merchant_stock';

        $variables = array_keys($this->ruleEngine->getAvailableVariables($contextType));

        return $this->ruleEngine->validateExpression($expression, $variables);
    }

    /**
     * 测试规则执行.
     *
     * @return array{success: bool, result: mixed, error: ?string, executionTimeMs: int, conditionMet: bool}
     */
    public function testRule(
        string $expression,
        ?string $conditionExpression,
        string $type,
        array $testContext = [],
    ): array {
        // 默认测试上下文
        $defaultContext = $type === MerchantRule::TYPE_PRICING
            ? ['cost' => 100, 'referencePrice' => 150, 'channelCode' => 'test', 'config' => []]
            : ['availableStock' => 100, 'totalStock' => 120, 'channelCode' => 'test', 'config' => []];

        $context = array_merge($defaultContext, $testContext);
        $initialValue = $type === MerchantRule::TYPE_PRICING ? 100.0 : 100;

        return $this->ruleEngine->testRule($expression, $conditionExpression, $context, $initialValue);
    }

    /**
     * 将分配转换为规则数组.
     *
     * @param MerchantRuleAssignment[] $assignments
     */
    private function convertAssignmentsToRules(array $assignments): array
    {
        $rules = [];

        foreach ($assignments as $assignment) {
            $rule = $assignment->getMerchantRule();
            $rules[] = [
                'ruleId' => $rule->getId(),
                'ruleType' => RuleExecutionLog::RULE_TYPE_MERCHANT,
                'expression' => $rule->getExpression(),
                'conditionExpression' => $rule->getConditionExpression(),
                'config' => $assignment->getMergedConfig(),
                'priority' => $assignment->getEffectivePriority(),
            ];
        }

        // 按优先级排序
        usort($rules, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return $rules;
    }
}
