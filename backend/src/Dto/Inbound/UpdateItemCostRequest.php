<?php

namespace App\Dto\Inbound;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateItemCostRequest
{
    #[Assert\NotBlank(message: 'validation.unit_cost_required')]
    #[Assert\Type('numeric', message: 'validation.unit_cost_must_numeric')]
    #[Assert\PositiveOrZero(message: 'validation.unit_cost_must_non_negative')]
    public string $unitCost = '';
}
