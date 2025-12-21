<?php

namespace App\Dto\Inbound;

use Symfony\Component\Validator\Constraints as Assert;

class ReceiveInboundItemRequest
{
    #[Assert\NotBlank(message: 'validation.item_id_required')]
    public string $itemId = '';

    #[Assert\NotBlank(message: 'validation.quantity_required')]
    #[Assert\PositiveOrZero(message: 'validation.quantity_must_non_negative')]
    public int $receivedQuantity = 0;

    #[Assert\PositiveOrZero(message: 'validation.damaged_quantity_must_non_negative')]
    public int $damagedQuantity = 0;

    #[Assert\Type('string')]
    public ?string $warehouseRemark = null;
}