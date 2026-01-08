<?php

namespace App\Dto\Merchant;

class ImportInventoryItemDto
{
    public function __construct(
        public readonly int $rowNumber,
        public readonly string $skuCode,
        public readonly int $quantity,
        public readonly ?string $unitCost = null,
        public readonly ?string $costCurrency = 'CNY',
        public readonly bool $isValid = true,
        public readonly ?string $errorMessage = null,
        public readonly ?string $productSkuId = null,
        public readonly ?string $productName = null,
        public readonly ?string $skuName = null,
        public readonly bool $exists = false,
    ) {
    }

    public function toArray(): array
    {
        return [
            'rowNumber' => $this->rowNumber,
            'skuCode' => $this->skuCode,
            'quantity' => $this->quantity,
            'unitCost' => $this->unitCost,
            'costCurrency' => $this->costCurrency,
            'isValid' => $this->isValid,
            'errorMessage' => $this->errorMessage,
            'productSkuId' => $this->productSkuId,
            'productName' => $this->productName,
            'skuName' => $this->skuName,
            'exists' => $this->exists,
        ];
    }
}
