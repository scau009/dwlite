<?php

namespace App\Dto\Admin;

use App\Enum\SizeUnit;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateProductSkuRequest
{
    #[Assert\Choice(callback: [SizeUnit::class, 'values'], message: 'validation.invalid_size_unit')]
    public ?string $sizeUnit = null;

    #[Assert\Length(max: 20, maxMessage: 'validation.size_value_max_length')]
    public ?string $sizeValue = null;

    /** @var array<string, string>|null */
    public ?array $specInfo = null;

    #[Assert\Positive(message: 'validation.price_positive')]
    public ?string $price = null;

    #[Assert\PositiveOrZero(message: 'validation.price_positive_or_zero')]
    public ?string $originalPrice = null;

    public ?bool $isActive = null;

    #[Assert\PositiveOrZero(message: 'validation.sort_order_positive')]
    public ?int $sortOrder = null;
}
