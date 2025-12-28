<?php

namespace App\Dto\Outbound;

use Symfony\Component\Validator\Constraints as Assert;

class OutboundOrderItemRequest
{
    #[Assert\NotBlank(message: 'validation.inventory_id_required')]
    public string $inventoryId = '';

    #[Assert\NotBlank(message: 'validation.quantity_required')]
    #[Assert\Positive(message: 'validation.quantity_must_positive')]
    public int $quantity = 0;
}
