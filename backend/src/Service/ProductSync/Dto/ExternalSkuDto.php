<?php

namespace App\Service\ProductSync\Dto;

/**
 * Data Transfer Object for external SKU data.
 *
 * Represents a single size/variant from an external data source.
 */
readonly class ExternalSkuDto
{
    public function __construct(
        public string $sizeValue,
        public string $sizeUnit,
        public ?string $price,
        public ?string $originalPrice,
        public ?string $barcode = null,
        public ?string $currency = '',
    ) {
    }

    /**
     * Create from KicksDB variant data (v3 API).
     *
     * @param array  $data        Variant data from KicksDB API
     * @param float  $avgPrice    Average price of the product (fallback)
     * @param float  $retailPrice Retail price of the product (fallback when price is 0)
     * @param string $currency    Currency code
     */
    public static function fromKicksDb(array $data, float $avgPrice, float $retailPrice, string $currency = 'USD'): self
    {
        // Normalize size type: "us m" -> "US", "us w" -> "US W"
        $sizeType = strtoupper($data['size_type'] ?? '');
        $isUsM = in_array($sizeType, ['US', 'US M'], true);
        $sizeUnit = $isUsM ? 'US' : ($data['size_type'] ?? '');

        // Price priority: lowest_ask > avgPrice > retailPrice (fallback)
        $lowestAsk = $data['lowest_ask'] ?? null;
        $price = $lowestAsk !== null ? (string) $lowestAsk : (string) $avgPrice;

        // If price is 0, use retail price as reference price
        if ((float) $price === 0.0 && $retailPrice > 0) {
            $price = (string) $retailPrice;
        }

        // originalPrice uses retailPrice if available, otherwise avgPrice
        $originalPrice = $retailPrice > 0 ? (string) $retailPrice : (string) $avgPrice;

        // Extract barcode from identifiers array (v3 API structure)
        $barcode = self::extractIdentifier($data);

        return new self(
            sizeValue: (string) ($data['size'] ?? ''),
            sizeUnit: $sizeUnit,
            price: $price,
            originalPrice: $originalPrice,
            barcode: $barcode,
            currency: $currency,
        );
    }

    /**
     * Extract UPC/GTIN identifier from variant identifiers.
     *
     * KicksDB v3 API returns identifiers as:
     * [{"identifier": "198483487781", "identifier_type": "UPC"}]
     */
    private static function extractIdentifier(array $data): ?string
    {
        if (!isset($data['identifiers']) || !is_array($data['identifiers'])) {
            return null;
        }

        // Prioritize UPC, then GTIN/EAN
        $priorityTypes = ['UPC', 'GTIN', 'EAN', 'EAN-13'];

        foreach ($priorityTypes as $type) {
            foreach ($data['identifiers'] as $identifier) {
                if (isset($identifier['identifier_type']) && $identifier['identifier_type'] === $type) {
                    return $identifier['identifier'] ?? null;
                }
            }
        }

        // Return first available identifier if no priority match
        if (!empty($data['identifiers'][0]['identifier'])) {
            return $data['identifiers'][0]['identifier'];
        }

        return null;
    }
}
