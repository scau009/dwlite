<?php

declare(strict_types=1);

namespace App\Service\Fulfillment\Dto;

use App\Entity\ChannelProductSource;

/**
 * 来源分配 - 表示从单个来源分配的数量.
 *
 * 用于支持寄售库存的拆分分配场景：
 * 一个订单项可能需要从多个来源分配不同数量来满足总需求。
 */
readonly class SourceAllocation
{
    public function __construct(
        public ChannelProductSource $source,
        public int $quantity,
        public float $score,
    ) {
    }

    public function getMerchantId(): string
    {
        return $this->source->getMerchant()->getId();
    }

    public function getWarehouseId(): string
    {
        $listing = $this->source->getInventoryListing();

        return $listing->getMerchantInventory()->getWarehouse()->getId();
    }

    public function getFulfillmentType(): string
    {
        return $this->source->getInventoryListing()->getFulfillmentType();
    }

    public function isConsignment(): bool
    {
        return $this->source->getInventoryListing()->isConsignment();
    }

    public function isSelfFulfillment(): bool
    {
        return $this->source->getInventoryListing()->isSelfFulfillment();
    }

    public function getPrice(): string
    {
        return $this->source->getMerchantPrice();
    }
}
