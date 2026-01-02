<?php

namespace App\Dto\Inbound;

use Symfony\Component\Validator\Constraints as Assert;

class CompleteInboundReceivingRequest
{
    /**
     * @var ReceiveInboundItemRequest[]
     */
    #[Assert\NotBlank(message: 'validation.items_required')]
    #[Assert\Type('array')]
    #[Assert\Count(min: 1, minMessage: 'validation.items_must_not_empty')]
    #[Assert\Valid]
    public array $items = [];

    #[Assert\Type('string')]
    public ?string $warehouseNotes = null;
}
