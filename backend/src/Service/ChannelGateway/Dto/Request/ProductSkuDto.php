<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Dto\Request;

/**
 * Product SKU DTO for push request.
 */
readonly class ProductSkuDto
{
    public function __construct(
        public string $sizeValue,
        public string $sizeUnit,
        public string $price,
        public ?string $compareAtPrice,
        public int $stock,
    ) {
    }
}
