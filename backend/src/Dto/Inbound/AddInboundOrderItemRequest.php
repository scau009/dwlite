<?php

namespace App\Dto\Inbound;

use Symfony\Component\Validator\Constraints as Assert;

class AddInboundOrderItemRequest
{
    #[Assert\NotBlank(message: 'validation.product_sku_id_required')]
    public string $productSkuId = '';

    #[Assert\NotBlank(message: 'validation.quantity_required')]
    #[Assert\Positive(message: 'validation.quantity_must_positive')]
    public int $expectedQuantity = 0;

    #[Assert\Type('numeric')]
    #[Assert\PositiveOrZero(message: 'validation.unit_cost_must_non_negative')]
    public ?string $unitCost = null;
}