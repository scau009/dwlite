<?php

namespace App\Service\ChannelGateway\Dto\Response;

/**
 * DTO for order line item pulled from channel.
 */
readonly class PulledOrderItemDto
{
    /**
     * @param array<string, mixed>|null $attributes Additional attributes
     */
    public function __construct(
        public string $externalProductId,
        public ?string $externalSkuId,
        public string $productName,
        public ?string $productImage,
        public int $quantity,
        public string $unitPrice,
        public string $totalPrice,
        public ?string $skuCode = null,
        public ?string $sizeValue = null,
        public ?array $attributes = null,
    ) {
    }
}
