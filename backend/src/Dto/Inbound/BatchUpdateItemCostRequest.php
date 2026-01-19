<?php

namespace App\Dto\Inbound;

use Symfony\Component\Validator\Constraints as Assert;

class BatchUpdateItemCostRequest
{
    /**
     * @var string[]
     */
    #[Assert\NotBlank(message: 'validation.item_ids_required')]
    #[Assert\Count(min: 1, minMessage: 'validation.item_ids_required')]
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Length(exactly: 26),
    ])]
    public array $itemIds = [];

    #[Assert\NotBlank(message: 'validation.unit_cost_required')]
    #[Assert\Type('numeric', message: 'validation.unit_cost_must_numeric')]
    #[Assert\PositiveOrZero(message: 'validation.unit_cost_must_non_negative')]
    public string $unitCost = '';
}
