<?php

namespace App\Dto\Inbound;

use Symfony\Component\Validator\Constraints as Assert;

class CreateInboundOrderRequest
{
    #[Assert\NotBlank(message: 'validation.warehouse_id_required')]
    public string $warehouseId = '';

    #[Assert\Type('string')]
    public ?string $merchantNotes = null;

    #[Assert\Type('DateTimeInterface')]
    public ?\DateTimeInterface $expectedArrivalDate = null;
}
