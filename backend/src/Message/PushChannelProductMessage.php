<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message to push channel product to external channel.
 *
 * After aggregation is complete, this message is dispatched to actually
 * sync the product data (create, update stock/price, or delist) to the
 * external sales channel via the ChannelGateway.
 */
readonly class PushChannelProductMessage implements AsyncMessageInterface
{
    public const OPERATION_PUSH_PRODUCT = 'push_product';
    public const OPERATION_UPDATE_STOCK_PRICE = 'update_stock_price';
    public const OPERATION_DELIST = 'delist';

    public function __construct(
        public string $channelProductId,
        public string $operation,
        public bool $forceFullSync = false,
    ) {
    }

    /**
     * Create a push product message.
     */
    public static function pushProduct(string $channelProductId, bool $force = false): self
    {
        return new self($channelProductId, self::OPERATION_PUSH_PRODUCT, $force);
    }

    /**
     * Create an update stock/price message.
     */
    public static function updateStockPrice(string $channelProductId, bool $force = false): self
    {
        return new self($channelProductId, self::OPERATION_UPDATE_STOCK_PRICE, $force);
    }

    /**
     * Create a delist message.
     */
    public static function delist(string $channelProductId): self
    {
        return new self($channelProductId, self::OPERATION_DELIST, false);
    }

    /**
     * Check if this is a push product operation.
     */
    public function isPushProduct(): bool
    {
        return $this->operation === self::OPERATION_PUSH_PRODUCT;
    }

    /**
     * Check if this is an update stock/price operation.
     */
    public function isUpdateStockPrice(): bool
    {
        return $this->operation === self::OPERATION_UPDATE_STOCK_PRICE;
    }

    /**
     * Check if this is a delist operation.
     */
    public function isDelist(): bool
    {
        return $this->operation === self::OPERATION_DELIST;
    }
}
