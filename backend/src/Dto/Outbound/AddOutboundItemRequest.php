<?php

namespace App\Dto\Outbound;

use App\Entity\OutboundOrderItem;
use Symfony\Component\Validator\Constraints as Assert;

class AddOutboundItemRequest
{
    #[Assert\NotBlank(message: 'validation.inventory_id_required')]
    #[Assert\Length(max: 26, maxMessage: 'validation.inventory_id_invalid')]
    public string $inventoryId;

    #[Assert\NotBlank(message: 'validation.quantity_required')]
    #[Assert\Positive(message: 'validation.quantity_positive')]
    public int $quantity;

    #[Assert\Choice(
        choices: [OutboundOrderItem::STOCK_TYPE_NORMAL, OutboundOrderItem::STOCK_TYPE_DAMAGED],
        message: 'validation.invalid_stock_type'
    )]
    public string $stockType = OutboundOrderItem::STOCK_TYPE_NORMAL;
}
