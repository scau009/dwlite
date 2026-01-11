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
