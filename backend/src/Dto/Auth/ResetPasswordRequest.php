<?php

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class ResetPasswordRequest
{
    #[Assert\NotBlank(message: 'validation.token_required')]
    public string $token = '';

    #[Assert\NotBlank(message: 'validation.password_required')]
    #[Assert\Length(min: 8, minMessage: 'validation.password_min_length')]
    #[Assert\Regex(
        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
        message: 'validation.password_complexity'
    )]
    public string $password = '';
}
