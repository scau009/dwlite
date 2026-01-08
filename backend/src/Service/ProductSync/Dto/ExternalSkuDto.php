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
     * Create from KicksDB size data.
     */
    public static function fromKicksDb(array $data,float $avgPrice): self
    {
        $isUs = in_array(strtoupper($data['size_type']), ['US', 'US M'], true);
        return new self(
            sizeValue: (string) ($data['size'] ?? ''),
            sizeUnit: $isUs ? 'US' : ($data['size_type'] ?? ''), // KicksDB uses US sizing
            price: $avgPrice,
            originalPrice: $avgPrice,
            barcode: $data['gtin'] ?? $data['upc'] ?? null,
            currency: 'USD',
        );
    }
}
