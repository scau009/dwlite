<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class ChargeDepositRequest
{
    #[Assert\NotBlank(message: 'amount field is required')]
    #[Assert\Regex(
        pattern: '/^-?\d+(\.\d{1,2})?$/',
        message: 'amount must be a valid decimal number with up to 2 decimal places'
    )]
    public string $amount = '';

    public ?string $remark = null;
}
