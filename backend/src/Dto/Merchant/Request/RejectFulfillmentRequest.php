<?php

declare(strict_types=1);

namespace App\Dto\Merchant\Request;

use Symfony\Component\Validator\Constraints as Assert;

class RejectFulfillmentRequest
{
    #[Assert\NotBlank(message: 'validation.reason_required')]
    #[Assert\Length(
        min: 5,
        max: 500,
        minMessage: 'validation.reason_too_short',
        maxMessage: 'validation.reason_too_long'
    )]
    public string $reason = '';
}
