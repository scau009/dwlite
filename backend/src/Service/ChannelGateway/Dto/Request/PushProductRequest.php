<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Dto\Request;

/**
 * Request DTO for pushing product to external channel.
 */
readonly class PushProductRequest
{
    /**
     * @param ProductImageDto[] $images
     * @param ProductSkuDto[]   $skus
     * @param array<string, mixed>|null $attributes Channel-specific attributes
     */
    public function __construct(
        public string $internalId,
        public ?string $externalId,
        public string $title,
        public string $description,
        public string $brand,
        public ?string $categoryCode,
        public array $images,
        public array $skus,
        public string $currency,
        public ?array $attributes = null,
    ) {
    }
}

/**
 * Product image DTO.
 */
readonly class ProductImageDto
{
    public function __construct(
        public string $url,
        public bool $isPrimary = false,
        public int $sortOrder = 0,
    ) {
    }
}

/**
 * Product SKU DTO for push request.
 */
readonly class ProductSkuDto
{
    public function __construct(
        public string $internalId,
        public ?string $externalId,
        public ?string $skuCode,
        public ?string $sizeValue,
        public string $price,
        public ?string $compareAtPrice,
        public int $stock,
        public ?string $barcode = null,
    ) {
    }
}
