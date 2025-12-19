<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class CreateProductSkuRequest
{
    #[Assert\NotBlank(message: 'validation.sku_code_required')]
    #[Assert\Length(max: 50, maxMessage: 'validation.sku_code_max_length')]
    public string $skuCode = '';

    #[Assert\Length(max: 20, maxMessage: 'validation.color_code_max_length')]
    public ?string $colorCode = null;

    #[Assert\Length(max: 20, maxMessage: 'validation.size_unit_max_length')]
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

    #[Assert\PositiveOrZero(message: 'validation.price_positive_or_zero')]
    public ?string $costPrice = null;

    public bool $isActive = true;

    #[Assert\PositiveOrZero(message: 'validation.sort_order_positive')]
    public int $sortOrder = 0;
}
