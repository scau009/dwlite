<?php

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class ForgotPasswordRequest
{
    #[Assert\NotBlank(message: 'validation.email_required')]
    #[Assert\Email(message: 'validation.email_invalid')]
    public string $email = '';
}
