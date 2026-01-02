<?php

namespace App\Dto\Inbound;

use Symfony\Component\Validator\Constraints as Assert;

class CreateInboundExceptionRequest
{
    #[Assert\NotBlank(message: 'validation.type_required')]
    #[Assert\Choice(
        choices: ['quantity_short', 'quantity_over', 'damaged', 'wrong_item', 'quality_issue', 'packaging', 'expired', 'other'],
        message: 'validation.invalid_exception_type'
    )]
    public string $type = '';

    #[Assert\NotBlank(message: 'validation.description_required')]
    public string $description = '';

    /**
     * @var array<array{sku_id?: string, expected?: int, actual?: int, issue?: string, order_item_id?: string, sku_name?: string, color_name?: string, product_name?: string, product_image?: string, quantity?: int}>
     */
    #[Assert\NotBlank(message: 'validation.items_required')]
    #[Assert\Type('array')]
    #[Assert\Count(min: 1, minMessage: 'validation.items_must_not_empty')]
    public array $items = [];

    /**
     * @var string[]|null
     */
    #[Assert\Type('array')]
    public ?array $evidenceImages = null;

    #[Assert\Type('string')]
    public ?string $reportedBy = null;
}