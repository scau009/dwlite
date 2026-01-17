<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Dto\Request;

/**
 * Request DTO for updating stock and/or price on external channel.
 *
 * @deprecated This class is no longer used. The updateStockPrice method now
 *             accepts ChannelProduct[] directly instead. Will be removed in
 *             a future version.
 */
readonly class UpdateStockPriceRequest
{
    /**
     * @param StockPriceUpdateDto[] $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}

/**
 * Single item stock/price update.
 *
 * @deprecated This class is no longer used. The updateStockPrice method now
 *             accepts ChannelProduct[] directly instead. Will be removed in
 *             a future version.
 */
readonly class StockPriceUpdateDto
{
    public function __construct(
        public string $externalId,
        public ?int $stock = null,
        public ?string $price = null,
        public ?string $compareAtPrice = null,
    ) {
    }

    public function hasStockUpdate(): bool
    {
        return $this->stock !== null;
    }

    public function hasPriceUpdate(): bool
    {
        return $this->price !== null;
    }
}
