<?php

namespace App\Dto\OpenApi\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

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
