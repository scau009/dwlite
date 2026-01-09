<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Provider\KicksCrew;

use App\Entity\ProductSku;
use App\Enum\SizeUnit;

/**
 * Mapper for converting between system SKU format and Kicks Crew format.
 *
 * Kicks Crew uses model_no + size_system + size as SKU identifier.
 */
class KicksCrewSkuMapper
{
    /**
     * Build external reference from internal listing ID.
     * Used to track listings on KC platform.
     *
     * Format: DWL-{listingId}
     */
    public function buildExtRef(string $inventoryListingId): string
    {
        return 'DWL-'.$inventoryListingId;
    }

    /**
     * Parse external reference to get internal listing ID.
     */
    public function parseExtRef(string $extRef): ?string
    {
        if (!str_starts_with($extRef, 'DWL-')) {
            return null;
        }

        return substr($extRef, 4);
    }

    /**
     * Get KC-compatible size system from SizeUnit.
     * KC supports: US, UK, EU (not CM).
     */
    public function getSizeSystem(?SizeUnit $sizeUnit): string
    {
        if ($sizeUnit === null) {
            return 'US'; // Default to US
        }

        return match ($sizeUnit) {
            SizeUnit::US => 'US',
            SizeUnit::UK => 'UK',
            SizeUnit::EU => 'EU',
            SizeUnit::CM => 'US', // CM not supported by KC, fallback to US
        };
    }

    /**
     * Check if size unit is directly supported by KC.
     */
    public function isSizeUnitSupported(?SizeUnit $sizeUnit): bool
    {
        if ($sizeUnit === null) {
            return true; // Will use default US
        }

        return in_array($sizeUnit, [SizeUnit::US, SizeUnit::UK, SizeUnit::EU], true);
    }

    /**
     * Build KC listing item from system data.
     *
     * @return array{model_no: string, size_system: string, size: string, qty: int, price: int, ext_ref?: string, brand?: string}
     */
    public function toKcListingItem(
        string $modelNo,
        string $sizeSystem,
        string $size,
        int $qty,
        int $price,
        ?string $extRef = null,
        ?string $brand = null,
    ): array {
        $item = [
            'model_no' => $modelNo,
            'size_system' => $sizeSystem,
            'size' => $size,
            'qty' => $qty,
            'price' => $price, // Must be integer USD
        ];

        if ($extRef !== null) {
            $item['ext_ref'] = $extRef;
        }

        if ($brand !== null) {
            $item['brand'] = strtoupper($brand);
        }

        return $item;
    }

    /**
     * Convert ProductSku to KC listing item.
     */
    public function skuToKcListingItem(
        ProductSku $sku,
        int $qty,
        int $price,
        ?string $extRef = null,
    ): array {
        $product = $sku->getProduct();

        return $this->toKcListingItem(
            modelNo: $product->getStyleNumber(),
            sizeSystem: $this->getSizeSystem($sku->getSizeUnit()),
            size: $sku->getSizeValue() ?? '',
            qty: $qty,
            price: $price,
            extRef: $extRef,
            brand: $product->getBrand()?->getName(),
        );
    }

    /**
     * Build KC SKU string from model_no and size.
     * Format: {model_no}-{size} where size is US size * 10 (e.g., 9 -> 90).
     *
     * @example DD1391-100-90 (model DD1391-100, US size 9)
     */
    public function buildKcSku(string $modelNo, string $usSize): string
    {
        // KC uses size * 10 format (e.g., "9" -> "90", "9.5" -> "95")
        $sizeCode = str_replace('.', '', (string) ((float) $usSize * 10));

        return sprintf('%s-%s', $modelNo, $sizeCode);
    }

    /**
     * Parse KC order size object to get size value.
     *
     * @param array{US?: string, UK?: string, EU?: string} $sizeObject
     */
    public function parseOrderSize(array $sizeObject, string $preferredSystem = 'US'): string
    {
        return $sizeObject[$preferredSystem] ?? $sizeObject['US'] ?? $sizeObject['EU'] ?? $sizeObject['UK'] ?? '';
    }

    /**
     * Convert price to KC format (integer USD, no decimals).
     */
    public function toKcPrice(string|float|int $price): int
    {
        return (int) round((float) $price);
    }
}
