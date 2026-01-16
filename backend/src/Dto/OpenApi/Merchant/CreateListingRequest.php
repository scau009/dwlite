<?php

namespace App\Dto\OpenApi\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request to create a listing.
 */
class CreateListingRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 26)]
    public string $inventoryId;

    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    public string $channelCode;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['consignment', 'self_fulfillment'])]
    public string $fulfillmentType;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['self_pricing', 'platform_managed'])]
    public string $pricingModel;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['shared', 'dedicated'])]
    public string $allocationMode;

    #[Assert\Type('integer')]
    #[Assert\Range(min: 0)]
    public ?int $allocatedQuantity = null;

    #[Assert\NotBlank]
    #[Assert\Type('numeric')]
    #[Assert\Range(min: 0)]
    public string $price;

    #[Assert\Type('numeric')]
    #[Assert\Range(min: 0)]
    public ?string $compareAtPrice = null;

    #[Assert\Length(max: 500)]
    public ?string $remark = null;
}
