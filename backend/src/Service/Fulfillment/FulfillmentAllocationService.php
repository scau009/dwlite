<?php

declare(strict_types=1);

namespace App\Service\Fulfillment;

use App\Entity\ChannelProductSource;
use App\Entity\Fulfillment;
use App\Entity\FulfillmentAllocationLog;
use App\Entity\FulfillmentItem;
use App\Entity\InventoryListing;
use App\Entity\Order;
use App\Entity\OrderException;
use App\Entity\OrderItem;
use App\Entity\PlatformRule;
use App\Repository\ChannelProductSourceRepository;
use App\Repository\FulfillmentAllocationLogRepository;
use App\Repository\FulfillmentRepository;
use App\Repository\OrderExceptionRepository;
use App\Repository\PlatformRuleRepository;
use App\Service\Fulfillment\Dto\AllocationResult;
use App\Service\Fulfillment\Dto\SourceSelectionResult;
use App\Service\RuleEngine\RuleEngineService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * 履约分配服务 - 负责订单的商户分配逻辑.
 */
class FulfillmentAllocationService
{
    private const LOCK_PREFIX = 'order_allocation_';
    private const LOCK_TTL = 60; // 锁定时间（秒）

    // 默认自履约响应时间（小时）
    private const DEFAULT_DEADLINE_HOURS = 24;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ChannelProductSourceRepository $sourceRepository,
        private readonly PlatformRuleRepository $ruleRepository,
        private readonly FulfillmentRepository $fulfillmentRepository,
        private readonly FulfillmentAllocationLogRepository $allocationLogRepository,
        private readonly OrderExceptionRepository $orderExceptionRepository,
        private readonly RuleEngineService $ruleEngine,
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 为订单分配商户.
     *
     * @param string[] $excludedMerchantIds 已排除的商户ID列表
     */
    public function allocateOrder(Order $order, array $excludedMerchantIds = [], int $attemptNumber = 1): AllocationResult
    {
        // 获取分布式锁，防止并发分配
        $lock = $this->lockFactory->createLock(
            self::LOCK_PREFIX.$order->getId(),
            self::LOCK_TTL
        );

        if (!$lock->acquire()) {
            $this->logger->warning('Failed to acquire allocation lock', [
                'orderId' => $order->getId(),
            ]);

            return AllocationResult::failure(
                $order,
                'Cannot acquire allocation lock, order may be processing',
                [],
                $excludedMerchantIds,
                $attemptNumber
            );
        }

        try {
            return $this->doAllocateOrder($order, $excludedMerchantIds, $attemptNumber);
        } finally {
            $lock->release();
        }
    }

    /**
     * 执行订单分配.
     */
    private function doAllocateOrder(Order $order, array $excludedMerchantIds, int $attemptNumber): AllocationResult
    {
        // 标记订单为分配中
        $order->markAllocating();
        $this->entityManager->flush();

        // 获取分配规则
        $allocationRules = $this->ruleRepository->findActiveByType(PlatformRule::TYPE_FULFILLMENT_ALLOCATION);

        // 为每个订单项选择最佳来源
        $itemResults = [];
        $allSuccess = true;

        foreach ($order->getItems() as $orderItem) {
            $result = $this->selectSourceForItem($orderItem, $excludedMerchantIds, $allocationRules, $attemptNumber);
            $itemResults[] = $result;

            if (!$result->success) {
                $allSuccess = false;
            }
        }

        // 如果有任何订单项分配失败
        if (!$allSuccess) {
            return $this->handleAllocationFailure($order, $itemResults, $excludedMerchantIds, $attemptNumber);
        }

        // 创建履约单
        $fulfillments = $this->createFulfillments($order, $itemResults, $excludedMerchantIds, $attemptNumber);

        // 标记订单已分配
        $order->markAllocated();
        $this->entityManager->flush();

        $this->logger->info('Order allocation successful', [
            'orderId' => $order->getId(),
            'fulfillmentCount' => count($fulfillments),
            'attemptNumber' => $attemptNumber,
        ]);

        return AllocationResult::success(
            $order,
            $fulfillments,
            $itemResults,
            $excludedMerchantIds,
            $attemptNumber
        );
    }

    /**
     * 为单个订单项选择最佳来源.
     *
     * @param PlatformRule[] $allocationRules
     */
    private function selectSourceForItem(
        OrderItem $orderItem,
        array $excludedMerchantIds,
        array $allocationRules,
        int $attemptNumber
    ): SourceSelectionResult {
        $channelProduct = $orderItem->getChannelProduct();

        if ($channelProduct === null) {
            $this->logAllocationFailure(
                $orderItem->getOrder(),
                $orderItem,
                $attemptNumber,
                FulfillmentAllocationLog::RESULT_NO_SOURCE,
                'Order item has no channel product',
                null
            );

            return SourceSelectionResult::failure(
                $orderItem,
                'Order item has no channel product'
            );
        }

        // 获取所有可用来源
        $availableSources = $this->getAvailableSources($channelProduct, $excludedMerchantIds, $orderItem->getQuantity());

        if (empty($availableSources)) {
            $this->logAllocationFailure(
                $orderItem->getOrder(),
                $orderItem,
                $attemptNumber,
                FulfillmentAllocationLog::RESULT_NO_SOURCE,
                'No available sources after excluding merchants',
                null
            );

            return SourceSelectionResult::failure(
                $orderItem,
                'No available sources for this product'
            );
        }

        // 计算每个来源的评分
        $scoredSources = $this->scoreAllSources($availableSources, $orderItem, $allocationRules);

        // 按评分排序选择最佳来源
        usort($scoredSources, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        // 构建候选来源列表用于日志
        $candidates = array_map(fn (array $s) => [
            'sourceId' => $s['source']->getId(),
            'merchantId' => $s['source']->getMerchant()->getId(),
            'score' => $s['score'],
            'available' => $s['source']->getAvailableQuantity(),
            'price' => $s['source']->getMerchantPrice(),
        ], $scoredSources);

        // 验证价格合理性
        $platformPrice = $channelProduct->getPrice();
        $selectedSource = null;

        foreach ($scoredSources as $scoredSource) {
            $source = $scoredSource['source'];
            $merchantPrice = $source->getMerchantPrice();

            // 价格验证：商户价格不能高于平台售价
            if (bccomp($merchantPrice, $platformPrice, 2) <= 0) {
                $selectedSource = $source;
                break;
            }
        }

        if ($selectedSource === null) {
            $this->logAllocationFailure(
                $orderItem->getOrder(),
                $orderItem,
                $attemptNumber,
                FulfillmentAllocationLog::RESULT_PRICE_INVALID,
                'All source prices exceed platform price',
                $candidates
            );

            return SourceSelectionResult::failure(
                $orderItem,
                'All source prices exceed platform price',
                $candidates
            );
        }

        // 记录成功日志
        $this->logAllocationSuccess(
            $orderItem->getOrder(),
            $orderItem,
            $attemptNumber,
            $selectedSource->getMerchant()->getId(),
            $selectedSource->getId(),
            $candidates
        );

        return SourceSelectionResult::success($orderItem, $selectedSource, $candidates);
    }

    /**
     * 获取可用的商品来源.
     *
     * @param string[] $excludedMerchantIds
     *
     * @return ChannelProductSource[]
     */
    private function getAvailableSources(
        \App\Entity\ChannelProduct $channelProduct,
        array $excludedMerchantIds,
        int $requiredQuantity
    ): array {
        // 获取所有活跃来源
        $sources = $this->sourceRepository->findActiveByProduct($channelProduct);

        // 过滤掉已排除的商户和库存不足的来源
        return array_filter($sources, function (ChannelProductSource $source) use ($excludedMerchantIds, $requiredQuantity) {
            // 检查商户是否被排除
            if (in_array($source->getMerchant()->getId(), $excludedMerchantIds, true)) {
                return false;
            }

            // 检查库存是否足够
            if ($source->getAvailableQuantity() < $requiredQuantity) {
                return false;
            }

            // 检查上架配置是否活跃
            $listing = $source->getInventoryListing();

            return $listing->isActive();
        });
    }

    /**
     * 为所有来源计算评分.
     *
     * @param ChannelProductSource[] $sources
     * @param PlatformRule[]         $rules
     *
     * @return array<array{source: ChannelProductSource, score: float}>
     */
    private function scoreAllSources(array $sources, OrderItem $orderItem, array $rules): array
    {
        $scoredSources = [];

        foreach ($sources as $source) {
            $score = $this->calculateSourceScore($source, $orderItem, $rules);
            $scoredSources[] = [
                'source' => $source,
                'score' => $score,
            ];
        }

        return $scoredSources;
    }

    /**
     * 计算单个来源的评分.
     *
     * @param PlatformRule[] $rules
     */
    private function calculateSourceScore(
        ChannelProductSource $source,
        OrderItem $orderItem,
        array $rules
    ): float {
        $listing = $source->getInventoryListing();
        $inventory = $listing->getMerchantInventory();
        $channelProduct = $orderItem->getChannelProduct();

        // 构建规则上下文
        $context = [
            'source' => [
                'merchantId' => $source->getMerchant()->getId(),
                'priority' => $source->getPriority(),
                'price' => $source->getMerchantPrice(),
                'availableQuantity' => $source->getAvailableQuantity(),
                'soldQuantity' => $source->getSoldQuantity(),
            ],
            'product' => [
                'platformPrice' => $channelProduct?->getPrice() ?? '0',
                'skuCode' => $orderItem->getSkuCode() ?? '',
                'categorySlug' => '', // 可从产品获取
            ],
            'fulfillmentType' => $listing->getFulfillmentType(),
            'order' => [
                'totalAmount' => $orderItem->getOrder()->getTotalAmount(),
                'itemCount' => $orderItem->getOrder()->getItems()->count(),
            ],
        ];

        // 默认评分（基于优先级）
        $defaultScore = 100.0 - (float) $source->getPriority();

        if (empty($rules)) {
            return $defaultScore;
        }

        // 应用规则计算评分
        $ruleData = [];
        foreach ($rules as $rule) {
            $ruleData[] = [
                'expression' => $rule->getExpression(),
                'conditionExpression' => $rule->getConditionExpression(),
                'config' => $rule->getConfig() ?? [],
                'ruleId' => $rule->getId(),
                'ruleType' => $rule->getType(),
            ];
        }

        try {
            $score = $this->ruleEngine->executeRuleChain(
                $ruleData,
                $context,
                $defaultScore,
                'fulfillment_allocation',
                $orderItem->getId()
            );

            return is_numeric($score) ? (float) $score : $defaultScore;
        } catch (\Throwable $e) {
            $this->logger->warning('Rule evaluation failed, using default score', [
                'sourceId' => $source->getId(),
                'error' => $e->getMessage(),
            ]);

            return $defaultScore;
        }
    }

    /**
     * 创建履约单.
     *
     * @param SourceSelectionResult[] $itemResults
     * @param string[]                $excludedMerchantIds
     *
     * @return Fulfillment[]
     */
    private function createFulfillments(
        Order $order,
        array $itemResults,
        array $excludedMerchantIds,
        int $attemptNumber
    ): array {
        // 按商户+仓库分组创建履约单
        $grouped = [];

        foreach ($itemResults as $result) {
            if (!$result->success || $result->selectedSource === null) {
                continue;
            }

            $key = $result->merchantId.'_'.$result->warehouseId.'_'.$result->fulfillmentType;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'merchantId' => $result->merchantId,
                    'warehouseId' => $result->warehouseId,
                    'fulfillmentType' => $result->fulfillmentType,
                    'items' => [],
                ];
            }
            $grouped[$key]['items'][] = $result;
        }

        $fulfillments = [];

        foreach ($grouped as $group) {
            $fulfillment = $this->createFulfillmentForGroup(
                $order,
                $group,
                $excludedMerchantIds,
                $attemptNumber
            );
            $fulfillments[] = $fulfillment;
        }

        return $fulfillments;
    }

    /**
     * 为一组订单项创建履约单.
     *
     * @param string[] $excludedMerchantIds
     */
    private function createFulfillmentForGroup(
        Order $order,
        array $group,
        array $excludedMerchantIds,
        int $attemptNumber
    ): Fulfillment {
        /** @var SourceSelectionResult[] $itemResults */
        $itemResults = $group['items'];
        $firstResult = $itemResults[0];
        $source = $firstResult->selectedSource;
        $listing = $source->getInventoryListing();
        $inventory = $listing->getMerchantInventory();

        // 创建履约单
        $fulfillment = new Fulfillment();
        $fulfillment->setOrder($order);
        $fulfillment->setWarehouse($inventory->getWarehouse());
        $fulfillment->setAllocationSource(Fulfillment::ALLOCATION_SOURCE_AUTO);
        $fulfillment->setAllocationAttempt($attemptNumber);
        $fulfillment->setExcludedMerchantIds($excludedMerchantIds ?: null);

        // 根据履约类型设置
        if ($listing->isConsignment()) {
            $fulfillment->setFulfillmentType(Fulfillment::TYPE_PLATFORM_WAREHOUSE);
        } else {
            $fulfillment->setFulfillmentType(Fulfillment::TYPE_MERCHANT_WAREHOUSE);
            $fulfillment->setMerchant($inventory->getMerchant());
            // 设置自履约响应截止时间
            $fulfillment->setDeadlineFromNow(self::DEFAULT_DEADLINE_HOURS);
        }

        // 添加履约单明细
        foreach ($itemResults as $result) {
            $fulfillmentItem = new FulfillmentItem();
            $fulfillmentItem->setOrderItem($result->orderItem);
            $fulfillmentItem->setQuantity($result->orderItem->getQuantity());

            // 快照来源信息
            $fulfillmentItem->snapshotFromSource($result->selectedSource);

            $fulfillment->addItem($fulfillmentItem);

            // 更新订单项分配数量
            $result->orderItem->addAllocatedQuantity($result->orderItem->getQuantity());

            // 记录来源销售
            $result->selectedSource->recordSale($result->orderItem->getQuantity());
        }

        // 添加到订单
        $order->addFulfillment($fulfillment);

        // 持久化
        $this->entityManager->persist($fulfillment);

        return $fulfillment;
    }

    /**
     * 处理分配失败.
     *
     * @param SourceSelectionResult[] $itemResults
     * @param string[]                $excludedMerchantIds
     */
    private function handleAllocationFailure(
        Order $order,
        array $itemResults,
        array $excludedMerchantIds,
        int $attemptNumber
    ): AllocationResult {
        $failedItems = array_filter($itemResults, fn ($r) => !$r->success);
        $reasons = array_map(fn ($r) => $r->failureReason, $failedItems);
        $reason = implode('; ', array_filter($reasons));

        // 检查是否所有商户都已尝试过
        $hasMoreMerchants = $this->hasMoreAvailableMerchants($order, $excludedMerchantIds);

        if (!$hasMoreMerchants) {
            // 所有商户都已尝试，创建异常工单
            $this->createOrderException(
                $order,
                OrderException::TYPE_ALLOCATION_EXHAUSTED,
                'All available merchants have been tried: '.$reason
            );

            $order->markAllocationFailed('All available merchants exhausted');
        } else {
            // 还有可用商户，创建异常工单
            $this->createOrderException(
                $order,
                OrderException::TYPE_NO_MERCHANT_AVAILABLE,
                'Current allocation attempt failed: '.$reason
            );

            $order->markAllocationFailed($reason);
        }

        $this->entityManager->flush();

        $this->logger->warning('Order allocation failed', [
            'orderId' => $order->getId(),
            'reason' => $reason,
            'attemptNumber' => $attemptNumber,
            'hasMoreMerchants' => $hasMoreMerchants,
        ]);

        return AllocationResult::failure(
            $order,
            $reason,
            $itemResults,
            $excludedMerchantIds,
            $attemptNumber
        );
    }

    /**
     * 检查是否还有未尝试的商户.
     *
     * @param string[] $excludedMerchantIds
     */
    private function hasMoreAvailableMerchants(Order $order, array $excludedMerchantIds): bool
    {
        foreach ($order->getItems() as $orderItem) {
            $channelProduct = $orderItem->getChannelProduct();
            if ($channelProduct === null) {
                continue;
            }

            $sources = $this->getAvailableSources($channelProduct, $excludedMerchantIds, $orderItem->getQuantity());
            if (!empty($sources)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 创建订单异常.
     */
    private function createOrderException(Order $order, string $type, string $description): void
    {
        $exception = new OrderException();
        $exception->setOrder($order);
        $exception->setType($type);
        $exception->setDescription($description);

        $this->entityManager->persist($exception);
    }

    /**
     * 记录分配成功日志.
     *
     * @param array<array{sourceId: string, merchantId: string, score: float, available: int, price: string}>|null $candidates
     */
    private function logAllocationSuccess(
        Order $order,
        OrderItem $orderItem,
        int $attemptNumber,
        string $merchantId,
        string $sourceId,
        ?array $candidates
    ): void {
        $log = FulfillmentAllocationLog::createSuccess(
            $order,
            $orderItem,
            $attemptNumber,
            $merchantId,
            $sourceId,
            $candidates
        );

        $this->entityManager->persist($log);
    }

    /**
     * 记录分配失败日志.
     *
     * @param array<array{sourceId: string, merchantId: string, score: float, available: int, price: string}>|null $candidates
     */
    private function logAllocationFailure(
        Order $order,
        OrderItem $orderItem,
        int $attemptNumber,
        string $result,
        string $reason,
        ?array $candidates
    ): void {
        $log = FulfillmentAllocationLog::createFailure(
            $order,
            $orderItem,
            $attemptNumber,
            $result,
            $reason,
            $candidates
        );

        $this->entityManager->persist($log);
    }

    /**
     * 处理履约拒绝（商户拒绝或超时）.
     */
    public function handleFulfillmentRejection(Fulfillment $fulfillment, string $reason, bool $isTimeout = false): void
    {
        $lock = $this->lockFactory->createLock(
            self::LOCK_PREFIX.$fulfillment->getOrder()->getId(),
            self::LOCK_TTL
        );

        if (!$lock->acquire()) {
            throw new \RuntimeException('Cannot acquire lock for fulfillment rejection');
        }

        try {
            $this->doHandleRejection($fulfillment, $reason, $isTimeout);
        } finally {
            $lock->release();
        }
    }

    /**
     * 执行拒绝处理.
     */
    private function doHandleRejection(Fulfillment $fulfillment, string $reason, bool $isTimeout): void
    {
        // 标记履约单状态
        if ($isTimeout) {
            $fulfillment->markExpired();
        } else {
            $fulfillment->markRejected($reason);
        }

        // 释放已锁定的库存
        $this->releaseAllocatedStock($fulfillment);

        // 将当前商户加入排除列表
        $excludedMerchantIds = $fulfillment->getExcludedMerchantIds() ?? [];
        if ($fulfillment->getMerchant() !== null) {
            $excludedMerchantIds[] = $fulfillment->getMerchant()->getId();
        }

        // 记录日志
        foreach ($fulfillment->getItems() as $item) {
            $this->logAllocationFailure(
                $fulfillment->getOrder(),
                $item->getOrderItem(),
                $fulfillment->getAllocationAttempt(),
                $isTimeout ? FulfillmentAllocationLog::RESULT_EXPIRED : FulfillmentAllocationLog::RESULT_REJECTED,
                $reason,
                null
            );
        }

        $this->entityManager->flush();

        $this->logger->info('Fulfillment rejected, will attempt reallocation', [
            'fulfillmentId' => $fulfillment->getId(),
            'orderId' => $fulfillment->getOrder()->getId(),
            'reason' => $reason,
            'isTimeout' => $isTimeout,
            'excludedMerchants' => $excludedMerchantIds,
        ]);
    }

    /**
     * 释放履约单已分配的库存.
     */
    private function releaseAllocatedStock(Fulfillment $fulfillment): void
    {
        foreach ($fulfillment->getItems() as $item) {
            // 减少订单项的已分配数量
            $orderItem = $item->getOrderItem();
            $newAllocated = max(0, $orderItem->getAllocatedQuantity() - $item->getQuantity());
            $orderItem->setAllocatedQuantity($newAllocated);

            // 注意：这里不直接操作库存，因为销售记录已经在 ChannelProductSource.recordSale 中完成
            // 如果需要回滚库存，应该通过 InventoryService 处理
        }
    }

    /**
     * 获取下一次分配尝试的参数.
     *
     * @return array{excludedMerchantIds: string[], attemptNumber: int}
     */
    public function getNextAllocationParams(Fulfillment $rejectedFulfillment): array
    {
        $excludedMerchantIds = $rejectedFulfillment->getExcludedMerchantIds() ?? [];

        if ($rejectedFulfillment->getMerchant() !== null) {
            $merchantId = $rejectedFulfillment->getMerchant()->getId();
            if (!in_array($merchantId, $excludedMerchantIds, true)) {
                $excludedMerchantIds[] = $merchantId;
            }
        }

        return [
            'excludedMerchantIds' => $excludedMerchantIds,
            'attemptNumber' => $rejectedFulfillment->getAllocationAttempt() + 1,
        ];
    }
}
