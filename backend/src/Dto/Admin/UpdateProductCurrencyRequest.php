<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateProductCurrencyRequest
{
    #[Assert\NotBlank(message: 'validation.currency_required')]
    #[Assert\Choice(choices: ['USD', 'CNY', 'EUR', 'GBP', 'JPY'], message: 'validation.invalid_currency')]
    public string $currency;
}
