<?php

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class VerifyEmailRequest
{
    #[Assert\NotBlank(message: 'Token is required')]
    public string $token = '';
}
