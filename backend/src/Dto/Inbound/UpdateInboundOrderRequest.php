<?php

namespace App\Dto\Inbound;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateInboundOrderRequest
{
    #[Assert\Type('string')]
    public ?string $merchantNotes = null;

    #[Assert\Type('DateTimeInterface')]
    public ?\DateTimeInterface $expectedArrivalDate = null;
}
