<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class BatchCreateSkuRequest
{
    #[Assert\NotBlank(message: 'validation.size_unit_required')]
    #[Assert\Choice(choices: ['US', 'EU', 'UK'], message: 'validation.invalid_quick_size_unit')]
    public string $sizeUnit;

    #[Assert\NotBlank(message: 'validation.price_required')]
    #[Assert\Positive(message: 'validation.price_positive')]
    public string $price;

    #[Assert\PositiveOrZero(message: 'validation.price_positive_or_zero')]
    public ?string $originalPrice = null;
}