<?php

namespace App\Dto\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

class CalculateListingDefaultsRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 26)]
    public string $merchantSalesChannelId;

    /**
     * @var string[]
     */
    #[Assert\NotBlank]
    #[Assert\Count(min: 1, max: 100, minMessage: 'At least one inventory ID is required', maxMessage: 'Cannot calculate more than 100 inventories at once')]
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Length(max: 26),
    ])]
    public array $inventoryIds = [];
}
