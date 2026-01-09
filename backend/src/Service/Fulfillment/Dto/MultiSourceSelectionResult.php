<?php

declare(strict_types=1);

namespace App\Service\Fulfillment\Dto;

use App\Entity\OrderItem;

/**
 * Multi-source selection result for an OrderItem.
 *
 * Supports both:
 * - Consignment: May split across multiple sources
 * - Self-fulfillment: Single source must satisfy full quantity
 */
readonly class MultiSourceSelectionResult
{
    /**
     * @param OrderItem                                                                                              $orderItem         订单项
     * @param SourceAllocation[]                                                                                     $allocations       分配列表 (can be multiple for consignment)
     * @param int                                                                                                    $totalAllocatedQty 总分配数量
     * @param bool                                                                                                   $success           是否成功满足需求
     * @param string|null                                                                                            $failureReason     失败原因
     * @param array<array{sourceId: string, merchantId: string, score: float, available: int, price: string}>|null $candidates        所有候选来源
     */
    public function __construct(
        public OrderItem $orderItem,
        public array $allocations = [],
        public int $totalAllocatedQty = 0,
        public bool $success = false,
        public ?string $failureReason = null,
        public ?array $candidates = null,
    ) {
    }

    /**
     * 创建成功结果.
     *
     * @param SourceAllocation[]                                                                                     $allocations
     * @param array<array{sourceId: string, merchantId: string, score: float, available: int, price: string}>|null $candidates
     */
    public static function success(
        OrderItem $orderItem,
        array $allocations,
        ?array $candidates = null
    ): self {
        $totalQty = array_reduce(
            $allocations,
            fn (int $sum, SourceAllocation $a) => $sum + $a->quantity,
            0
        );

        return new self(
            orderItem: $orderItem,
            allocations: $allocations,
            totalAllocatedQty: $totalQty,
            success: true,
            candidates: $candidates,
        );
    }

    /**
     * 创建失败结果.
     *
     * @param array<array{sourceId: string, merchantId: string, score: float, available: int, price: string}>|null $candidates
     */
    public static function failure(
        OrderItem $orderItem,
        string $reason,
        ?array $candidates = null
    ): self {
        return new self(
            orderItem: $orderItem,
            success: false,
            failureReason: $reason,
            candidates: $candidates,
        );
    }

    /**
     * 是否完全分配.
     */
    public function isFullyAllocated(): bool
    {
        return $this->totalAllocatedQty >= $this->orderItem->getQuantity();
    }

    /**
     * 是否包含寄售分配.
     */
    public function hasConsignmentAllocations(): bool
    {
        foreach ($this->allocations as $allocation) {
            if ($allocation->isConsignment()) {
                return true;
            }
        }

        return false;
    }

    /**
     * 是否包含自履约分配.
     */
    public function hasSelfFulfillmentAllocations(): bool
    {
        foreach ($this->allocations as $allocation) {
            if ($allocation->isSelfFulfillment()) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取第一个分配的商户ID（用于日志记录）.
     */
    public function getFirstMerchantId(): ?string
    {
        if (empty($this->allocations)) {
            return null;
        }

        return $this->allocations[0]->getMerchantId();
    }

    /**
     * 获取第一个分配的来源ID（用于日志记录）.
     */
    public function getFirstSourceId(): ?string
    {
        if (empty($this->allocations)) {
            return null;
        }

        return $this->allocations[0]->source->getId();
    }
}
