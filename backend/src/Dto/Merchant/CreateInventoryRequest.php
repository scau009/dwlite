<?php

namespace App\Dto\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

class CreateInventoryRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 26)]
    public string $warehouseId;

    #[Assert\NotBlank]
    #[Assert\Length(max: 26)]
    public string $productSkuId;

    #[Assert\NotBlank]
    #[Assert\Positive]
    public int $quantity;

    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/', message: 'Unit cost must be a valid decimal number')]
    public ?string $unitCost = null;

    #[Assert\Length(max: 3)]
    public ?string $costCurrency = null;

    #[Assert\Length(max: 500)]
    public ?string $notes = null;
}
