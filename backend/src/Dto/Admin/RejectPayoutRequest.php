<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class RejectPayoutRequest
{
    #[Assert\NotBlank(message: 'Reject reason is required')]
    #[Assert\Length(max: 255)]
    public string $reason;
}
