<?php

namespace App\Dto\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

class AdjustInventoryRequest
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['set', 'increase', 'decrease'])]
    public string $adjustmentType;

    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    public int $quantity;

    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/', message: 'Unit cost must be a valid decimal number')]
    public ?string $unitCost = null;

    #[Assert\Length(max: 500)]
    public ?string $notes = null;
}
