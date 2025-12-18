<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class ChargeDepositRequest
{
    #[Assert\NotBlank(message: 'validation.amount_required')]
    #[Assert\Regex(
        pattern: '/^-?\d+(\.\d{1,2})?$/',
        message: 'validation.amount_format'
    )]
    public string $amount = '';

    public ?string $remark = null;
}
