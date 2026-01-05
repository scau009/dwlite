<?php

namespace App\Dto\Admin;

use App\Enum\SizeUnit;
use Symfony\Component\Validator\Constraints as Assert;

class CreateProductSkuRequest
{
    #[Assert\Choice(callback: [SizeUnit::class, 'values'], message: 'validation.invalid_size_unit')]
    public ?string $sizeUnit = null;

    #[Assert\Length(max: 20, maxMessage: 'validation.size_value_max_length')]
    public ?string $sizeValue = null;

    /** @var array<string, string>|null */
    public ?array $specInfo = null;

    #[Assert\NotBlank(message: 'validation.price_required')]
    #[Assert\Positive(message: 'validation.price_positive')]
    public string $price = '0.00';

    #[Assert\PositiveOrZero(message: 'validation.price_positive_or_zero')]
    public ?string $originalPrice = null;

    #[Assert\Length(exactly: 3, exactMessage: 'validation.currency_length')]
    #[Assert\Regex(pattern: '/^[A-Z]{3}$/', message: 'validation.currency_format')]
    public string $currency = 'USD';

    #[Assert\Length(max: 50, maxMessage: 'validation.barcode_max_length')]
    public ?string $barcode = null;

    public bool $isActive = true;

    #[Assert\PositiveOrZero(message: 'validation.sort_order_positive')]
    public int $sortOrder = 0;
}
