<?php

declare(strict_types=1);

namespace App\Service\Fulfillment;

use App\Entity\ChannelProductSource;
use App\Entity\Fulfillment;
use App\Entity\FulfillmentAllocationLog;
use App\Entity\FulfillmentItem;
use App\Entity\Order;
use App\Entity\OrderException;
use App\Entity\OrderItem;
use App\Entity\PlatformRule;
use App\Message\ProcessConsignmentFulfillmentMessage;
use App\Repository\ChannelProductSourceRepository;
use App\Repository\InventoryReservationRepository;
use App\Repository\PlatformRuleRepository;
use App\Service\BusinessNoGenerator;
use App\Service\Fulfillment\Dto\AllocationResult;
use App\Service\Fulfillment\Dto\MultiSourceSelectionResult;
use App\Service\Fulfillment\Dto\SourceAllocation;
use App\Service\InventoryReservationService;
use App\Service\OpenApi\WebhookService;
use App\Service\RuleEngine\PlatformRuleService;
use App\Service\RuleEngine\RuleEngineService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;

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
        private readonly InventoryReservationRepository $reservationRepository,
        private readonly PlatformRuleRepository $ruleRepository,
        private readonly RuleEngineService $ruleEngine,
        private readonly InventoryReservationService $reservationService,
        private readonly LockFactory $lockFactory,
        private readonly MessageBusInterface $messageBus,
        private readonly WebhookService $webhookService,
        private readonly BusinessNoGenerator $businessNoGenerator,
        private readonly PlatformRuleService $platformRuleService,
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
     *
     * 使用新的多来源分配逻辑：
     * - 自履约库存：必须单个来源满足全部数量
     * - 寄售库存：可以从多个来源拆分分配
     */
    private function doAllocateOrder(Order $order, array $excludedMerchantIds, int $attemptNumber): AllocationResult
    {
        // 标记订单为分配中
        $order->markAllocating();
        $this->entityManager->flush();

        // 获取分配规则
        $allocationRules = $this->ruleRepository->findActiveByType(PlatformRule::TYPE_FULFILLMENT_ALLOCATION);

        // 为每个订单项选择来源（支持拆分分配）
        /** @var MultiSourceSelectionResult[] $itemResults */
        $itemResults = [];
        $allSuccess = true;

        foreach ($order->getItems() as $orderItem) {
            $result = $this->selectSourcesForItem($orderItem, $excludedMerchantIds, $allocationRules, $attemptNumber);
            $itemResults[] = $result;

            if (!$result->success) {
                $allSuccess = false;
            }
        }

        // 如果有任何订单项分配失败
        if (!$allSuccess) {
            return $this->handleMultiSourceAllocationFailure($order, $itemResults, $excludedMerchantIds, $attemptNumber);
        }

        // 创建履约单（使用新的多来源方法）
        $fulfillments = $this->createFulfillmentsFromMultiSource($order, $itemResults, $excludedMerchantIds, $attemptNumber);

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
     * 为单个订单项选择来源 - 支持寄售拆分分配.
     *
     * 分配规则：
     * 1. 自履约库存：必须单个来源满足全部数量，不能拆分
     * 2. 寄售库存：可以从多个来源拆分分配
     *
     * @param PlatformRule[] $allocationRules
     */
    private function selectSourcesForItem(
        OrderItem $orderItem,
        array $excludedMerchantIds,
        array $allocationRules,
        int $attemptNumber
    ): MultiSourceSelectionResult {
        $channelProduct = $orderItem->getChannelProduct();
        $requiredQuantity = $orderItem->getQuantity();

        if ($channelProduct === null) {
            $this->logAllocationFailure(
                $orderItem->getOrder(),
                $orderItem,
                $attemptNumber,
                FulfillmentAllocationLog::RESULT_NO_SOURCE,
                'Order item has no channel product',
                null
            );

            return MultiSourceSelectionResult::failure(
                $orderItem,
                'Order item has no channel product'
            );
        }

        // 获取所有可用来源（不过滤数量）
        $availableSources = $this->getAvailableSources($channelProduct, $excludedMerchantIds);

        if (empty($availableSources)) {
            $this->logAllocationFailure(
                $orderItem->getOrder(),
                $orderItem,
                $attemptNumber,
                FulfillmentAllocationLog::RESULT_NO_SOURCE,
                'No available sources after excluding merchants',
                null
            );

            return MultiSourceSelectionResult::failure(
                $orderItem,
                'No available sources for this product'
            );
        }

        // 计算每个来源的评分
        $scoredSources = $this->scoreAllSources($availableSources, $orderItem, $allocationRules);
        usort($scoredSources, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        // 构建候选来源列表用于日志
        $candidates = $this->buildCandidatesList($scoredSources);

        // 统一按评分分配（不再区分履约类型优先级）
        $platformPrice = $channelProduct->getPlatformPrice();
        $allocations = $this->allocateByUnifiedScore(
            $scoredSources,
            $requiredQuantity,
            $platformPrice
        );

        $totalAllocated = array_reduce(
            $allocations,
            fn (int $sum, SourceAllocation $a) => $sum + $a->quantity,
            0
        );

        if ($totalAllocated >= $requiredQuantity) {
            // 记录每个分配的成功日志
            foreach ($allocations as $allocation) {
                $this->logAllocationSuccess(
                    $orderItem->getOrder(),
                    $orderItem,
                    $attemptNumber,
                    $allocation->getMerchantId(),
                    $allocation->source->getId(),
                    $candidates
                );
            }

            return MultiSourceSelectionResult::success($orderItem, $allocations, $candidates);
        }

        // 分配失败
        $this->logAllocationFailure(
            $orderItem->getOrder(),
            $orderItem,
            $attemptNumber,
            FulfillmentAllocationLog::RESULT_NO_SOURCE,
            'Insufficient stock across all available sources',
            $candidates
        );

        return MultiSourceSelectionResult::failure(
            $orderItem,
            'Cannot fulfill required quantity from available sources',
            $candidates
        );
    }

    /**
     * 统一评分分配算法 - 按评分高低遍历所有来源，不区分类型.
     *
     * 分配规则：
     * - 自履约来源：必须满足全部剩余数量才能选中
     * - 寄售来源：可以部分分配
     *
     * @param array<array{source: ChannelProductSource, score: float}> $scoredSources
     *
     * @return SourceAllocation[]
     */
    private function allocateByUnifiedScore(
        array $scoredSources,
        int $requiredQuantity,
        string $platformPrice
    ): array {
        $allocations = [];
        $remainingQuantity = $requiredQuantity;

        foreach ($scoredSources as $scored) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $source = $scored['source'];
            $listing = $source->getInventoryListing();
            $merchantPrice = $source->getMerchantPrice();

            // 价格验证
            if (bccomp($merchantPrice, $platformPrice, 2) > 0) {
                continue;
            }

            $availableQty = $source->getAvailableQuantity();
            if ($availableQty <= 0) {
                continue;
            }

            if ($listing->isSelfFulfillment()) {
                // 自履约：必须满足全部剩余数量
                if ($availableQty >= $remainingQuantity) {
                    $allocations[] = new SourceAllocation($source, $remainingQuantity, $scored['score']);
                    $remainingQuantity = 0;
                    break;
                }
            // 不能部分分配，跳过
            } else {
                // 寄售：可以部分分配
                $allocateQty = min($availableQty, $remainingQuantity);
                $allocations[] = new SourceAllocation($source, $allocateQty, $scored['score']);
                $remainingQuantity -= $allocateQty;
            }
        }

        return $allocations;
    }

    /**
     * 构建候选来源列表用于日志.
     *
     * @param array<array{source: ChannelProductSource, score: float}> $scoredSources
     *
     * @return array<array{sourceId: string, merchantId: string, score: float, available: int, price: string, fulfillmentType: string}>
     */
    private function buildCandidatesList(array $scoredSources): array
    {
        return array_map(fn (array $s) => [
            'sourceId' => $s['source']->getId(),
            'merchantId' => $s['source']->getMerchant()->getId(),
            'score' => $s['score'],
            'available' => $s['source']->getAvailableQuantity(),
            'price' => $s['source']->getMerchantPrice(),
            'fulfillmentType' => $s['source']->getInventoryListing()->getFulfillmentType(),
        ], $scoredSources);
    }

    /**
     * 获取可用的商品来源（不再要求库存满足全部需求，支持拆分分配）.
     *
     * @param string[] $excludedMerchantIds
     *
     * @return ChannelProductSource[]
     */
    private function getAvailableSources(
        \App\Entity\ChannelProduct $channelProduct,
        array $excludedMerchantIds
    ): array {
        // 获取所有活跃来源
        $sources = $this->sourceRepository->findActiveByProduct($channelProduct);

        // 过滤掉已排除的商户和无库存的来源
        return array_filter($sources, function (ChannelProductSource $source) use ($excludedMerchantIds) {
            // 检查商户是否被排除
            if (in_array($source->getMerchant()->getId(), $excludedMerchantIds, true)) {
                return false;
            }

            // 只需要有库存即可（支持部分分配）
            if ($source->getAvailableQuantity() <= 0) {
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
                'platformPrice' => $channelProduct?->getPlatformPrice() ?? '0',
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
     * 从多来源分配结果创建履约单.
     *
     * @param MultiSourceSelectionResult[] $itemResults
     * @param string[]                     $excludedMerchantIds
     *
     * @return Fulfillment[]
     */
    private function createFulfillmentsFromMultiSource(
        Order $order,
        array $itemResults,
        array $excludedMerchantIds,
        int $attemptNumber
    ): array {
        // 按商户+仓库+履约类型分组
        $grouped = [];

        foreach ($itemResults as $result) {
            if (!$result->success) {
                continue;
            }

            // 每个分配项可能来自不同的商户/仓库
            foreach ($result->allocations as $allocation) {
                $key = $allocation->getMerchantId().'_'.$allocation->getWarehouseId().'_'.$allocation->getFulfillmentType();

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'merchantId' => $allocation->getMerchantId(),
                        'warehouseId' => $allocation->getWarehouseId(),
                        'fulfillmentType' => $allocation->getFulfillmentType(),
                        'isConsignment' => $allocation->isConsignment(),
                        'allocations' => [],
                    ];
                }

                // 存储分配及其关联的订单项
                $grouped[$key]['allocations'][] = [
                    'allocation' => $allocation,
                    'orderItem' => $result->orderItem,
                ];
            }
        }

        $fulfillments = [];

        foreach ($grouped as $group) {
            $fulfillment = $this->createFulfillmentForAllocationGroup(
                $order,
                $group,
                $excludedMerchantIds,
                $attemptNumber
            );
            $fulfillments[] = $fulfillment;

            // 寄售履约单自动触发出库单创建
            if ($group['isConsignment']) {
                $this->messageBus->dispatch(
                    new ProcessConsignmentFulfillmentMessage($fulfillment->getId())
                );
            }
        }

        return $fulfillments;
    }

    /**
     * 为一组分配项创建履约单.
     *
     * @param array{merchantId: string, warehouseId: string, fulfillmentType: string, isConsignment: bool, allocations: array<array{allocation: SourceAllocation, orderItem: OrderItem}>} $group
     * @param string[]                                                                                                                                                                   $excludedMerchantIds
     */
    private function createFulfillmentForAllocationGroup(
        Order $order,
        array $group,
        array $excludedMerchantIds,
        int $attemptNumber
    ): Fulfillment {
        $firstAllocationData = $group['allocations'][0];
        /** @var SourceAllocation $firstAllocation */
        $firstAllocation = $firstAllocationData['allocation'];
        $listing = $firstAllocation->source->getInventoryListing();
        $inventory = $listing->getMerchantInventory();

        // 创建履约单
        $fulfillment = new Fulfillment();
        $fulfillment->setFulfillmentNo($this->businessNoGenerator->generateFulfillmentNo());
        $fulfillment->setOrder($order);
        $fulfillment->setWarehouse($inventory->getWarehouse());
        $fulfillment->setAllocationSource(Fulfillment::ALLOCATION_SOURCE_AUTO);
        $fulfillment->setAllocationAttempt($attemptNumber);
        $fulfillment->setExcludedMerchantIds($excludedMerchantIds ?: null);

        // 根据履约类型设置
        if ($group['isConsignment']) {
            $fulfillment->setFulfillmentType(Fulfillment::TYPE_PLATFORM_WAREHOUSE);
        } else {
            $fulfillment->setFulfillmentType(Fulfillment::TYPE_MERCHANT_WAREHOUSE);
            $fulfillment->setMerchant($inventory->getMerchant());
            // 设置自履约响应截止时间
            $fulfillment->setDeadlineFromNow(self::DEFAULT_DEADLINE_HOURS);
        }

        // 添加履约单明细
        foreach ($group['allocations'] as $allocationData) {
            /** @var SourceAllocation $allocation */
            $allocation = $allocationData['allocation'];
            /** @var OrderItem $orderItem */
            $orderItem = $allocationData['orderItem'];

            $fulfillmentItem = new FulfillmentItem();
            $fulfillmentItem->setOrderItem($orderItem);
            $fulfillmentItem->setQuantity($allocation->quantity); // 使用分配的数量，不是订单项的全部数量

            // 快照来源信息
            $fulfillmentItem->snapshotFromSource($allocation->source);

            // 设置结算价格和佣金率
            $settlementPrice = $fulfillmentItem->getListPrice() ?? '0.00';
            $fulfillmentItem->setSettlementPrice($settlementPrice);

            // 从平台规则获取佣金率
            $merchant = $fulfillmentItem->getMerchant();
            if ($merchant !== null) {
                $channelCode = $order->getSalesChannel()->getCode();
                $commissionRate = $this->platformRuleService->getSettlementFeeRate(
                    $merchant->getId(),
                    $channelCode
                );
                $fulfillmentItem->setCommissionRate($commissionRate);

                // 计算佣金金额
                $fulfillmentItem->calculateSettlement();
            }

            $fulfillment->addItem($fulfillmentItem);

            // 更新订单项分配数量
            $orderItem->addAllocatedQuantity($allocation->quantity);

            // 记录来源销售
            $allocation->source->recordSale($allocation->quantity);

            // 分配预留（第二层：MerchantInventory 层）
            $reservation = $this->reservationRepository->findActiveByOrderItem($orderItem);
            if ($reservation !== null && $reservation->isReserved()) {
                try {
                    $this->reservationService->allocateReservation(
                        $reservation,
                        $inventory,
                        $fulfillment,
                        $fulfillmentItem
                    );
                    $this->logger->info('Reservation allocated to fulfillment item', [
                        'reservationId' => $reservation->getId(),
                        'fulfillmentItemId' => $fulfillmentItem->getId(),
                        'quantity' => $reservation->getQuantity(),
                    ]);
                } catch (\LogicException $e) {
                    $this->logger->error('Failed to allocate reservation', [
                        'reservationId' => $reservation->getId(),
                        'fulfillmentItemId' => $fulfillmentItem->getId(),
                        'error' => $e->getMessage(),
                    ]);
                    // 继续处理，不中断分配流程
                }
            }
        }

        // 添加到订单
        $order->addFulfillment($fulfillment);

        // 持久化
        $this->entityManager->persist($fulfillment);

        // Trigger webhook for merchant (only for self-fulfillment)
        if (!$group['isConsignment'] && $fulfillment->getMerchant() !== null) {
            $this->webhookService->triggerMerchantEvent(
                \App\Entity\Webhook::EVENT_FULFILLMENT_CREATED,
                $fulfillment->getMerchant(),
                [
                    'fulfillment_no' => $fulfillment->getFulfillmentNo(),
                    'order_external_id' => $order->getExternalOrderId(),
                    'sales_channel' => $order->getSalesChannel()->getCode(),
                    'warehouse_id' => $fulfillment->getWarehouse()->getId(),
                    'warehouse_code' => $fulfillment->getWarehouse()->getCode(),
                    'fulfillment_type' => $fulfillment->getFulfillmentType(),
                    'deadline_at' => $fulfillment->getDeadlineAt()?->format(\DateTimeInterface::ATOM),
                    'items_count' => $fulfillment->getItems()->count(),
                    'total_amount' => $fulfillment->getTotalAmount(),
                    'status' => $fulfillment->getStatus(),
                ]
            );
        }

        return $fulfillment;
    }

    /**
     * 处理分配失败（支持多来源结果）.
     *
     * @param MultiSourceSelectionResult[] $itemResults
     * @param string[]                     $excludedMerchantIds
     */
    private function handleMultiSourceAllocationFailure(
        Order $order,
        array $itemResults,
        array $excludedMerchantIds,
        int $attemptNumber
    ): AllocationResult {
        $failedItems = array_filter($itemResults, fn ($r) => !$r->success);
        $reasons = array_map(fn ($r) => $r->failureReason, $failedItems);
        $reason = implode('; ', array_filter($reasons));

        // 检查是否所有商户都已尝试过
        $hasMoreMerchants = $this->hasMoreAvailableMerchantsCombined($order, $excludedMerchantIds);

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
     * 检查是否还有未尝试的商户（考虑组合库存）.
     *
     * @param string[] $excludedMerchantIds
     */
    private function hasMoreAvailableMerchantsCombined(Order $order, array $excludedMerchantIds): bool
    {
        foreach ($order->getItems() as $orderItem) {
            $channelProduct = $orderItem->getChannelProduct();
            if ($channelProduct === null) {
                continue;
            }

            // 获取所有有库存的来源（不过滤数量）
            $sources = $this->getAvailableSources($channelProduct, $excludedMerchantIds);

            // 检查组合库存是否满足需求
            $totalAvailable = 0;
            foreach ($sources as $source) {
                $totalAvailable += $source->getAvailableQuantity();
            }

            if ($totalAvailable >= $orderItem->getQuantity()) {
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
        $exception->setExceptionNo($this->businessNoGenerator->generateOrderExceptionNo());
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

    /**
     * 为展示计算并排序来源评分（公开方法，供 API 使用）.
     *
     * @return array<array{source: ChannelProductSource, score: float}>
     */
    public function scoreSourcesForDisplay(\App\Entity\ChannelProduct $channelProduct): array
    {
        $rules = $this->ruleRepository->findActiveByType(PlatformRule::TYPE_FULFILLMENT_ALLOCATION);
        $sources = $channelProduct->getSources()->toArray();

        $scoredSources = [];
        foreach ($sources as $source) {
            $score = $this->calculateDisplayScore($source, $channelProduct, $rules);
            $scoredSources[] = [
                'source' => $source,
                'score' => $score,
            ];
        }

        usort($scoredSources, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return $scoredSources;
    }

    /**
     * 计算来源展示评分（无订单上下文）.
     *
     * 使用中性/默认值填充订单相关变量，用于展示来源的预估分配优先级。
     *
     * @param PlatformRule[] $rules
     */
    private function calculateDisplayScore(
        ChannelProductSource $source,
        \App\Entity\ChannelProduct $channelProduct,
        array $rules
    ): float {
        $listing = $source->getInventoryListing();

        // 构建展示用上下文（无订单上下文，使用中性值）
        $context = [
            'source' => [
                'merchantId' => $source->getMerchant()->getId(),
                'priority' => $source->getPriority(),
                'price' => $source->getMerchantPrice(),
                'availableQuantity' => $source->getAvailableQuantity(),
                'soldQuantity' => $source->getSoldQuantity(),
            ],
            'product' => [
                'platformPrice' => $channelProduct->getPlatformPrice(),
                'skuCode' => $channelProduct->getProductSku()->getSkuName(),
                'categorySlug' => '',
            ],
            'fulfillmentType' => $listing->getFulfillmentType(),
            'order' => [
                'totalAmount' => '0.00',
                'itemCount' => 1,
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
                'fulfillment_allocation_display',
                $source->getId()
            );

            return is_numeric($score) ? (float) $score : $defaultScore;
        } catch (\Throwable $e) {
            $this->logger->warning('Display score calculation failed, using default score', [
                'sourceId' => $source->getId(),
                'error' => $e->getMessage(),
            ]);

            return $defaultScore;
        }
    }
}
