<?php

namespace App\Dto\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

class CreateListingRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 26)]
    public string $merchantInventoryId;

    #[Assert\NotBlank]
    #[Assert\Length(max: 26)]
    public string $merchantSalesChannelId;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['consignment', 'self_fulfillment'])]
    public string $fulfillmentType;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['self_pricing', 'platform_managed'])]
    public string $pricingModel;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['shared', 'dedicated'])]
    public string $allocationMode;

    #[Assert\PositiveOrZero]
    public ?int $allocatedQuantity = null;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/', message: 'Price must be a valid decimal number')]
    public string $price;

    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/', message: 'Compare at price must be a valid decimal number')]
    public ?string $compareAtPrice = null;

    public ?string $remark = null;
}
