<?php

namespace App\Dto\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

class BatchCreateListingRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 26)]
    public string $merchantSalesChannelId;

    /**
     * @var BatchListingItemRequest[]
     */
    #[Assert\NotBlank]
    #[Assert\Count(min: 1, max: 100, minMessage: 'At least one listing is required', maxMessage: 'Cannot create more than 100 listings at once')]
    #[Assert\Valid]
    public array $listings = [];
}
