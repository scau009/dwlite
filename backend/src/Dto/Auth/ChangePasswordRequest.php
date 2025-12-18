<?php

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordRequest
{
    #[Assert\NotBlank(message: 'validation.current_password_required')]
    public string $currentPassword = '';

    #[Assert\NotBlank(message: 'validation.new_password_required')]
    #[Assert\Length(min: 8, minMessage: 'validation.password_min_length')]
    #[Assert\Regex(
        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
        message: 'validation.password_complexity'
    )]
    public string $newPassword = '';
}
