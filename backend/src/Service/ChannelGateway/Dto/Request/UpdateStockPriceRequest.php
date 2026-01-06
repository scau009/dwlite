<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Dto\Request;

/**
 * Request DTO for updating stock and/or price on external channel.
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
