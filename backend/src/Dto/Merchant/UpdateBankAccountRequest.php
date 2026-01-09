<?php

declare(strict_types=1);

namespace App\Dto\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateBankAccountRequest
{
    #[Assert\NotBlank(message: 'validation.bank_name_required')]
    #[Assert\Length(max: 100, maxMessage: 'validation.bank_name_max_length')]
    public string $bankName = '';

    #[Assert\Length(max: 20, maxMessage: 'validation.bank_code_max_length')]
    public ?string $bankCode = null;

    #[Assert\Length(max: 200, maxMessage: 'validation.branch_name_max_length')]
    public ?string $branchName = null;

    #[Assert\NotBlank(message: 'validation.account_number_required')]
    #[Assert\Length(max: 50, maxMessage: 'validation.account_number_max_length')]
    public string $accountNumber = '';

    #[Assert\NotBlank(message: 'validation.account_holder_required')]
    #[Assert\Length(max: 100, maxMessage: 'validation.account_holder_max_length')]
    public string $accountHolder = '';

    #[Assert\NotBlank(message: 'validation.account_type_required')]
    #[Assert\Choice(choices: ['corporate', 'personal'], message: 'validation.account_type_invalid')]
    public string $accountType = '';

    #[Assert\Length(max: 3)]
    public string $currency = 'CNY';
}
