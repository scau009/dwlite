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
    ) {
    }

    /**
     * Create from KicksDB size data.
     */
    public static function fromKicksDb(array $data, ?float $retailPrice = null): self
    {
        return new self(
            sizeValue: (string) ($data['size'] ?? ''),
            sizeUnit: 'US', // KicksDB uses US sizing
            price: isset($data['lowestAsk']) ? (string) $data['lowestAsk'] : null,
            originalPrice: $retailPrice !== null ? (string) $retailPrice : null,
            barcode: $data['gtin'] ?? $data['upc'] ?? null,
        );
    }
}
