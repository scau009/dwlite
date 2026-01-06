<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class BatchUpdateSkuRequest
{
    /**
     * @var string[]
     */
    #[Assert\NotBlank(message: 'validation.at_least_one_sku')]
    #[Assert\Count(min: 1, minMessage: 'validation.at_least_one_sku')]
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Type('string'),
    ])]
    public array $skuIds = [];

    #[Assert\Positive(message: 'validation.price_positive')]
    public ?string $price = null;

    #[Assert\PositiveOrZero(message: 'validation.price_positive_or_zero')]
    public ?string $originalPrice = null;

    public ?bool $isActive = null;

    public ?string $barcode = null;
}
