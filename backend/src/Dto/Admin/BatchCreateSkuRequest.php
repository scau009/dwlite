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

    #[Assert\Length(exactly: 3, exactMessage: 'validation.currency_length')]
    #[Assert\Regex(pattern: '/^[A-Z]{3}$/', message: 'validation.currency_format')]
    public string $currency = 'USD';
}
