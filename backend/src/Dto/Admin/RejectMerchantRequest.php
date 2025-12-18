<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class RejectMerchantRequest
{
    #[Assert\NotBlank(message: 'validation.reason_required')]
    #[Assert\Length(max: 255, maxMessage: 'validation.reason_too_long')]
    public string $reason = '';
}
