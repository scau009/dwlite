<?php

declare(strict_types=1);

namespace App\Dto\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

class CreatePayoutRequest
{
    #[Assert\NotBlank(message: 'validation.bank_account_required')]
    public string $bankAccountId = '';

    #[Assert\NotBlank(message: 'validation.amount_required')]
    #[Assert\Positive(message: 'validation.amount_positive')]
    public string $amount = '';

    #[Assert\Length(max: 500, maxMessage: 'validation.remark_max_length')]
    public ?string $remark = null;
}
