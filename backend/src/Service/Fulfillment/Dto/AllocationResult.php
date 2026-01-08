<?php

declare(strict_types=1);

namespace App\Service\Fulfillment\Dto;

use App\Entity\Fulfillment;
use App\Entity\Order;

/**
 * 订单分配结果 - 整个订单的分配结果.
 */
readonly class AllocationResult
{
    /**
     * @param Order                                $order                 订单
     * @param bool                                 $success               是否成功
     * @param Fulfillment[]                        $fulfillments          创建的履约单列表
     * @param SourceSelectionResult[]              $itemResults           每个订单项的分配结果
     * @param string|null                          $failureReason         失败原因
     * @param string[]                             $excludedMerchantIds   已排除的商户ID列表
     * @param int                                  $attemptNumber         当前尝试次数
     */
    public function __construct(
        public Order $order,
        public bool $success,
        public array $fulfillments = [],
        public array $itemResults = [],
        public ?string $failureReason = null,
        public array $excludedMerchantIds = [],
        public int $attemptNumber = 1,
    ) {
    }

    /**
     * 创建成功结果.
     *
     * @param Fulfillment[]           $fulfillments
     * @param SourceSelectionResult[] $itemResults
     * @param string[]                $excludedMerchantIds
     */
    public static function success(
        Order $order,
        array $fulfillments,
        array $itemResults,
        array $excludedMerchantIds = [],
        int $attemptNumber = 1
    ): self {
        return new self(
            order: $order,
            success: true,
            fulfillments: $fulfillments,
            itemResults: $itemResults,
            excludedMerchantIds: $excludedMerchantIds,
            attemptNumber: $attemptNumber,
        );
    }

    /**
     * 创建失败结果.
     *
     * @param SourceSelectionResult[] $itemResults
     * @param string[]                $excludedMerchantIds
     */
    public static function failure(
        Order $order,
        string $reason,
        array $itemResults = [],
        array $excludedMerchantIds = [],
        int $attemptNumber = 1
    ): self {
        return new self(
            order: $order,
            success: false,
            itemResults: $itemResults,
            failureReason: $reason,
            excludedMerchantIds: $excludedMerchantIds,
            attemptNumber: $attemptNumber,
        );
    }

    /**
     * 获取所有涉及的商户ID.
     *
     * @return string[]
     */
    public function getMerchantIds(): array
    {
        $merchantIds = [];
        foreach ($this->fulfillments as $fulfillment) {
            if ($merchantId = $fulfillment->getMerchant()?->getId()) {
                $merchantIds[] = $merchantId;
            }
        }

        return array_unique($merchantIds);
    }

    /**
     * 是否有自履约订单.
     */
    public function hasSelfFulfillment(): bool
    {
        foreach ($this->fulfillments as $fulfillment) {
            if ($fulfillment->isMerchantWarehouse()) {
                return true;
            }
        }

        return false;
    }

    /**
     * 是否全部为寄售订单.
     */
    public function isAllConsignment(): bool
    {
        foreach ($this->fulfillments as $fulfillment) {
            if (!$fulfillment->isPlatformWarehouse()) {
                return false;
            }
        }

        return true;
    }

    /**
     * 获取失败的订单项.
     *
     * @return SourceSelectionResult[]
     */
    public function getFailedItems(): array
    {
        return array_filter(
            $this->itemResults,
            fn (SourceSelectionResult $result) => !$result->success
        );
    }

    /**
     * 获取成功的订单项.
     *
     * @return SourceSelectionResult[]
     */
    public function getSuccessfulItems(): array
    {
        return array_filter(
            $this->itemResults,
            fn (SourceSelectionResult $result) => $result->success
        );
    }
}
