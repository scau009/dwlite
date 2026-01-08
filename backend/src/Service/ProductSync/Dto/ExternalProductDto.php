<?php

namespace App\Service\ProductSync\Dto;

/**
 * Data Transfer Object for external product data.
 *
 * This DTO standardizes product data from different external sources
 * into a common format that the sync service can process.
 */
readonly class ExternalProductDto
{
    /**
     * @param string             $externalId   Unique ID in the external system
     * @param string             $styleId      Style/SKU number (used for matching)
     * @param string             $title        Product title/name
     * @param string|null        $brand        Brand name
     * @param string|null        $productType  Product type (e.g., 'sneakers', 'apparel')
     * @param string|null        $description  Product description
     * @param string|null        $color        Color name
     * @param string|null        $imageUrl     Primary image URL
     * @param string|null        $externalUrl  URL to product page in external system
     * @param ExternalSkuDto[]   $skus         Array of SKU/size variants
     * @param string             $currency     Currency code (e.g., 'USD')
     * @param array              $rawData      Original raw data for storage
     */
    public function __construct(
        public string $externalId,
        public string $styleId,
        public string $title,
        public ?string $brand,
        public ?string $productType,
        public ?string $description,
        public ?string $color,
        public ?string $imageUrl,
        public ?string $externalUrl,
        public array $skus,
        public string $currency,
        public array $rawData,
    ) {
    }

    /**
     * Create from KicksDB API response.
     */
    public static function fromKicksDb(array $data): self
    {
        $skus = [];
        $avgPrice = $data['avg_price'] ?? 0;
        if (isset($data['variants']) && is_array($data['variants'])) {
            foreach ($data['variants'] as $variant) {
                $skus[] = ExternalSkuDto::fromKicksDb($variant,$avgPrice);
            }
        }

        // Extract color from product attributes if available
        $color = null;

        // Build external URL
        $externalUrl = null;

        return new self(
            externalId: $data['productId'] ?? $data['id'] ?? '',
            styleId: $data['sku'] ?? '',
            title: $data['title'] ?? $data['name'] ?? '',
            brand: $data['brand'] ?? null,
            productType: $data['product_type'] ?? null,
            description: $data['description'] ?? null,
            color: $color,
            imageUrl: $data['image'] ?? $data['thumbnail'] ?? null,
            externalUrl: $externalUrl,
            skus: $skus,
            currency: 'USD',
            rawData: $data,
        );
    }

    /**
     * Check if the product has a valid style ID for matching.
     */
    public function hasValidStyleId(): bool
    {
        return !empty($this->styleId) && $this->styleId !== 'N/A';
    }
}
