<?php

namespace App\Service\RuleEngine;

use App\Entity\ChannelProduct;
use App\Entity\InventoryListing;
use App\Entity\PlatformRule;
use App\Entity\PlatformRuleAssignment;
use App\Entity\RuleExecutionLog;
use App\Repository\PlatformRuleAssignmentRepository;
use App\Repository\PlatformRuleRepository;

/**
 * 平台规则服务.
 *
 * 处理平台配置的加价规则、库存优先级规则和结算费率规则
 */
class PlatformRuleService
{
    public function __construct(
        private readonly RuleEngineService $ruleEngine,
        private readonly PlatformRuleRepository $ruleRepository,
        private readonly PlatformRuleAssignmentRepository $assignmentRepository,
    ) {
    }

    /**
     * 计算平台售价.
     *
     * 在商户报价基础上应用平台加价规则
     *
     * @return string 计算后的平台售价
     */
    public function calculatePlatformPrice(
        ChannelProduct $channelProduct,
        InventoryListing $listing,
    ): string {
        $merchantChannel = $listing->getMerchantSalesChannel();
        $merchant = $merchantChannel->getMerchant();
        $salesChannel = $channelProduct->getSalesChannel();
        $sku = $channelProduct->getProductSku();
        $product = $sku->getProduct();
        $inventory = $listing->getMerchantInventory();

        $merchantPrice = (float) $listing->getPrice();
        $cost = (float) ($inventory->getAverageCost() ?? '0');

        // 构建上下文
        $context = [
            'merchantPrice' => $merchantPrice,
            'cost' => $cost,
            'referencePrice' => (float) $sku->getPrice(),
            'brand' => $product->getBrand()?->getName(),
            'brandSlug' => $product->getBrand()?->getSlug(),
            'category' => $product->getCategory()?->getName(),
            'categorySlug' => $product->getCategory()?->getSlug(),
            'merchantId' => $merchant->getId(),
            'channelCode' => $salesChannel->getCode(),
            'fulfillmentType' => $merchantChannel->getFulfillmentType(),
            'pricingModel' => $merchantChannel->getPricingModel(),
        ];

        // 收集适用的规则（商户级别 + 商品级别）
        $rules = $this->collectPricingRules($merchant->getId(), $channelProduct->getId());

        if (empty($rules)) {
            // 没有配置规则，直接返回商户报价
            return bcmul((string) $merchantPrice, '1', 2);
        }

        // 执行规则链
        $result = $this->ruleEngine->executeRuleChain(
            $rules,
            $context,
            $merchantPrice,
            RuleExecutionLog::CONTEXT_PRICING,
            $channelProduct->getId()
        );

        // 确保价格有效
        return bcmul((string) max(0, $result), '1', 2);
    }

    /**
     * 计算结算费用.
     *
     * 根据平台费率规则计算向商户收取的手续费
     *
     * @return string 计算后的手续费
     */
    public function calculateSettlementFee(
        string $merchantId,
        string $channelCode,
        float $orderAmount,
        ?string $channelProductId = null,
    ): string {
        // 构建上下文
        $context = [
            'orderAmount' => $orderAmount,
            'merchantId' => $merchantId,
            'channelCode' => $channelCode,
        ];

        // 收集适用的费率规则
        $rules = $this->collectSettlementRules($merchantId, $channelProductId);

        if (empty($rules)) {
            // 没有配置规则，返回0手续费
            return '0.00';
        }

        // 执行规则链（初始值为订单金额，用于计算费率）
        $result = $this->ruleEngine->executeRuleChain(
            $rules,
            $context,
            $orderAmount,
            RuleExecutionLog::CONTEXT_SETTLEMENT,
            $channelProductId
        );

        // 手续费 = 结果 - 订单金额（如果规则计算的是扣除后金额）
        // 或者直接返回结果（如果规则计算的就是手续费）
        // 这里假设规则直接计算手续费
        return bcmul((string) max(0, $result), '1', 2);
    }

    /**
     * 获取平台规则列表.
     *
     * @return array{data: PlatformRule[], total: int}
     */
    public function getRules(
        int $page = 1,
        int $limit = 20,
        ?string $type = null,
        ?string $search = null,
    ): array {
        return $this->ruleRepository->findPaginated($page, $limit, $type, $search);
    }

    /**
     * 获取规则的分配列表.
     *
     * @return PlatformRuleAssignment[]
     */
    public function getRuleAssignments(PlatformRule $rule): array
    {
        return $this->assignmentRepository->findByRule($rule);
    }

    /**
     * 获取范围的规则分配列表.
     *
     * @return PlatformRuleAssignment[]
     */
    public function getAssignmentsByScope(string $scopeType, string $scopeId): array
    {
        return $this->assignmentRepository->findByScope($scopeType, $scopeId);
    }

    /**
     * 验证规则表达式.
     *
     * @return array{valid: bool, error: ?string}
     */
    public function validateExpression(string $expression, string $type): array
    {
        $contextType = match ($type) {
            PlatformRule::TYPE_PRICING => 'platform_pricing',
            PlatformRule::TYPE_SETTLEMENT_FEE => 'platform_settlement',
            default => 'platform_pricing',
        };

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
        $defaultContext = match ($type) {
            PlatformRule::TYPE_PRICING => [
                'merchantPrice' => 100,
                'cost' => 80,
                'referencePrice' => 120,
                'brand' => 'Nike',
                'brandSlug' => 'nike',
                'category' => 'Sneakers',
                'categorySlug' => 'sneakers',
                'merchantId' => 'test-merchant',
                'channelCode' => 'test',
                'config' => [],
            ],
            PlatformRule::TYPE_SETTLEMENT_FEE => [
                'orderAmount' => 100,
                'merchantId' => 'test-merchant',
                'channelCode' => 'test',
                'config' => [],
            ],
            default => ['config' => []],
        };

        $context = array_merge($defaultContext, $testContext);
        $initialValue = $type === PlatformRule::TYPE_SETTLEMENT_FEE
            ? ($context['orderAmount'] ?? 100.0)
            : ($context['merchantPrice'] ?? 100.0);

        return $this->ruleEngine->testRule($expression, $conditionExpression, $context, $initialValue);
    }

    /**
     * 收集定价规则.
     *
     * 按优先级顺序收集商户级别和商品级别的加价规则
     */
    private function collectPricingRules(string $merchantId, string $channelProductId): array
    {
        $rules = [];

        // 1. 商户级别的规则
        $merchantAssignments = $this->assignmentRepository->findActiveByScopeAndType(
            PlatformRuleAssignment::SCOPE_MERCHANT,
            $merchantId,
            PlatformRule::TYPE_PRICING
        );
        $rules = array_merge($rules, $this->convertAssignmentsToRules($merchantAssignments));

        // 2. 商品级别的规则（优先级更高）
        $productAssignments = $this->assignmentRepository->findActiveByScopeAndType(
            PlatformRuleAssignment::SCOPE_CHANNEL_PRODUCT,
            $channelProductId,
            PlatformRule::TYPE_PRICING
        );
        $rules = array_merge($rules, $this->convertAssignmentsToRules($productAssignments));

        // 按优先级排序
        usort($rules, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return $rules;
    }

    /**
     * 收集结算费率规则.
     */
    private function collectSettlementRules(string $merchantId, ?string $channelProductId): array
    {
        $rules = [];

        // 1. 商户级别的规则
        $merchantAssignments = $this->assignmentRepository->findActiveByScopeAndType(
            PlatformRuleAssignment::SCOPE_MERCHANT,
            $merchantId,
            PlatformRule::TYPE_SETTLEMENT_FEE
        );
        $rules = array_merge($rules, $this->convertAssignmentsToRules($merchantAssignments));

        // 2. 商品级别的规则（如果有）
        if ($channelProductId) {
            $productAssignments = $this->assignmentRepository->findActiveByScopeAndType(
                PlatformRuleAssignment::SCOPE_CHANNEL_PRODUCT,
                $channelProductId,
                PlatformRule::TYPE_SETTLEMENT_FEE
            );
            $rules = array_merge($rules, $this->convertAssignmentsToRules($productAssignments));
        }

        // 按优先级排序
        usort($rules, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return $rules;
    }

    /**
     * 将分配转换为规则数组.
     *
     * @param PlatformRuleAssignment[] $assignments
     */
    private function convertAssignmentsToRules(array $assignments): array
    {
        $rules = [];

        foreach ($assignments as $assignment) {
            $rule = $assignment->getPlatformRule();
            $rules[] = [
                'ruleId' => $rule->getId(),
                'ruleType' => RuleExecutionLog::RULE_TYPE_PLATFORM,
                'expression' => $rule->getExpression(),
                'conditionExpression' => $rule->getConditionExpression(),
                'config' => $assignment->getMergedConfig(),
                'priority' => $assignment->getEffectivePriority(),
            ];
        }

        return $rules;
    }
}
