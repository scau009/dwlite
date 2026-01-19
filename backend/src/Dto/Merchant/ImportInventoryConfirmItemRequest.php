<?php

namespace App\Dto\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

class ImportInventoryConfirmItemRequest
{
    #[Assert\NotBlank]
    public string $skuCode;

    #[Assert\NotBlank]
    #[Assert\Length(max: 26)]
    public string $productSkuId;

    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    public int $quantity;

    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/')]
    public ?string $unitCost = null;

    #[Assert\Length(max: 10)]
    public ?string $costCurrency = null;
}
