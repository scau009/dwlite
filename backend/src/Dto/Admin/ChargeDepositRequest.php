<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class ChargeDepositRequest
{
    #[Assert\NotNull(message: 'validation.amount_required')]
    #[Assert\Positive(message: 'validation.amount_positive')]
    public float $amount = 0.00;

    public ?string $remark = null;
}
