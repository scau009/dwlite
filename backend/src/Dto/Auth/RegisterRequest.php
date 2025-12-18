<?php

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class RegisterRequest
{
    #[Assert\NotBlank(message: 'validation.email_required')]
    #[Assert\Email(message: 'validation.email_invalid')]
    public string $email = '';

    #[Assert\NotBlank(message: 'validation.password_required')]
    #[Assert\Length(min: 8, minMessage: 'validation.password_min_length')]
    #[Assert\Regex(
        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
        message: 'validation.password_complexity'
    )]
    public string $password = '';
}
