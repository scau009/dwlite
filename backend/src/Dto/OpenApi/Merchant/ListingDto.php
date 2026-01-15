<?php

namespace App\Dto\OpenApi\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Query parameters for listing listings.
 */
class ListingQuery
{
    #[Assert\Type('integer')]
    #[Assert\Range(min: 1)]
    public int $page = 1;

    #[Assert\Type('integer')]
    #[Assert\Range(min: 1, max: 100)]
    public int $pageSize = 20;

    #[Assert\Choice(choices: ['draft', 'active', 'paused', 'sold_out'])]
    public ?string $status = null;

    #[Assert\Length(max: 50)]
    public ?string $channelCode = null;

    #[Assert\Length(max: 100)]
    public ?string $sku = null;
}

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

/**
 * Request to update a listing.
 */
class UpdateListingRequest
{
    #[Assert\NotBlank]
    #[Assert\Type('numeric')]
    #[Assert\Range(min: 0)]
    public string $price;

    #[Assert\Type('numeric')]
    #[Assert\Range(min: 0)]
    public ?string $compareAtPrice = null;

    #[Assert\Type('integer')]
    #[Assert\Range(min: 0)]
    public ?int $allocatedQuantity = null;

    #[Assert\Length(max: 500)]
    public ?string $remark = null;
}
