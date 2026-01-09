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
     * Create from KicksDB API response (v3).
     *
     * @param array  $data     Product data from KicksDB API
     * @param string $currency Currency code for the response
     */
    public static function fromKicksDb(array $data, string $currency = 'USD'): self
    {
        $skus = [];
        $avgPrice = (float) ($data['avg_price'] ?? 0);

        if (isset($data['variants']) && is_array($data['variants'])) {
            foreach ($data['variants'] as $variant) {
                // Skip hidden variants
                if (isset($variant['hidden']) && $variant['hidden'] === true) {
                    continue;
                }
                $skus[] = ExternalSkuDto::fromKicksDb($variant, $avgPrice, $currency);
            }
        }

        // Extract color from traits if available
        $color = self::extractTraitValue($data, 'Colorway');

        return new self(
            externalId: $data['id'] ?? '',
            styleId: $data['sku'] ?? '',
            title: $data['title'] ?? '',
            brand: $data['brand'] ?? null,
            productType: $data['product_type'] ?? null,
            description: $data['short_description'] ?? $data['description'] ?? null,
            color: $color,
            imageUrl: $data['image'] ?? null,
            externalUrl: $data['link'] ?? null,
            skus: $skus,
            currency: $currency,
            rawData: $data,
        );
    }

    /**
     * Extract a trait value from product data.
     */
    private static function extractTraitValue(array $data, string $traitName): ?string
    {
        if (!isset($data['traits']) || !is_array($data['traits'])) {
            return null;
        }

        foreach ($data['traits'] as $trait) {
            if (isset($trait['trait']) && $trait['trait'] === $traitName) {
                return $trait['value'] ?? null;
            }
        }

        return null;
    }

    /**
     * Check if the product has a valid style ID for matching.
     */
    public function hasValidStyleId(): bool
    {
        return !empty($this->styleId) && $this->styleId !== 'N/A';
    }
}
