<?php

namespace App\Dto\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateListingRequest
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/', message: 'Price must be a valid decimal number')]
    public string $price;

    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/', message: 'Compare at price must be a valid decimal number')]
    public ?string $compareAtPrice = null;

    #[Assert\PositiveOrZero]
    public ?int $allocatedQuantity = null;

    public ?string $remark = null;
}
