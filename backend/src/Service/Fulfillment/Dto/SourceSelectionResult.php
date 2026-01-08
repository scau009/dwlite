<?php

declare(strict_types=1);

namespace App\Service\Fulfillment\Dto;

use App\Entity\ChannelProductSource;
use App\Entity\InventoryListing;
use App\Entity\MerchantInventory;
use App\Entity\OrderItem;

/**
 * 来源选择结果 - 单个订单项的分配结果.
 */
readonly class SourceSelectionResult
{
    /**
     * @param OrderItem                              $orderItem        订单项
     * @param ChannelProductSource|null              $selectedSource   选中的来源
     * @param string|null                            $merchantId       商户ID
     * @param string|null                            $warehouseId      仓库ID
     * @param string|null                            $fulfillmentType  履约类型 (consignment/self_fulfillment)
     * @param string|null                            $price            选中来源的价格
     * @param array<array{sourceId: string, merchantId: string, score: float, available: int, price: string}>|null $candidates 候选来源列表
     * @param bool                                   $success          是否成功
     * @param string|null                            $failureReason    失败原因
     */
    public function __construct(
        public OrderItem $orderItem,
        public ?ChannelProductSource $selectedSource = null,
        public ?string $merchantId = null,
        public ?string $warehouseId = null,
        public ?string $fulfillmentType = null,
        public ?string $price = null,
        public ?array $candidates = null,
        public bool $success = false,
        public ?string $failureReason = null,
    ) {
    }

    /**
     * 创建成功结果.
     *
     * @param array<array{sourceId: string, merchantId: string, score: float, available: int, price: string}>|null $candidates
     */
    public static function success(
        OrderItem $orderItem,
        ChannelProductSource $source,
        ?array $candidates = null
    ): self {
        $listing = $source->getInventoryListing();
        $inventory = $listing?->getMerchantInventory();

        return new self(
            orderItem: $orderItem,
            selectedSource: $source,
            merchantId: $inventory?->getMerchant()?->getId(),
            warehouseId: $inventory?->getWarehouse()?->getId(),
            fulfillmentType: $listing?->getFulfillmentType(),
            price: $source->getPrice(),
            candidates: $candidates,
            success: true,
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
            candidates: $candidates,
            success: false,
            failureReason: $reason,
        );
    }

    /**
     * 是否为寄售模式.
     */
    public function isConsignment(): bool
    {
        return $this->fulfillmentType === 'consignment';
    }

    /**
     * 是否为自履约模式.
     */
    public function isSelfFulfillment(): bool
    {
        return $this->fulfillmentType === 'self_fulfillment';
    }

    /**
     * 获取关联的 InventoryListing.
     */
    public function getInventoryListing(): ?InventoryListing
    {
        return $this->selectedSource?->getInventoryListing();
    }

    /**
     * 获取关联的 MerchantInventory.
     */
    public function getMerchantInventory(): ?MerchantInventory
    {
        return $this->getInventoryListing()?->getMerchantInventory();
    }
}
