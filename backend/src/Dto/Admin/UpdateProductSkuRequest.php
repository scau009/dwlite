<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateProductSkuRequest
{
    #[Assert\Length(max: 20, maxMessage: 'validation.color_code_max_length')]
    public ?string $colorCode = null;

    #[Assert\Length(max: 20, maxMessage: 'validation.size_unit_max_length')]
    public ?string $sizeUnit = null;

    #[Assert\Length(max: 20, maxMessage: 'validation.size_value_max_length')]
    public ?string $sizeValue = null;

    /** @var array<string, string>|null */
    public ?array $specInfo = null;

    #[Assert\Positive(message: 'validation.price_positive')]
    public ?string $price = null;

    #[Assert\PositiveOrZero(message: 'validation.price_positive_or_zero')]
    public ?string $originalPrice = null;

    #[Assert\PositiveOrZero(message: 'validation.price_positive_or_zero')]
    public ?string $costPrice = null;

    public ?bool $isActive = null;

    #[Assert\PositiveOrZero(message: 'validation.sort_order_positive')]
    public ?int $sortOrder = null;
}
