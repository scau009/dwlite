<?php

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordRequest
{
    #[Assert\NotBlank(message: 'Current password is required')]
    public string $currentPassword = '';

    #[Assert\NotBlank(message: 'New password is required')]
    #[Assert\Length(min: 8, minMessage: 'Password must be at least 8 characters')]
    #[Assert\Regex(
        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
        message: 'Password must contain at least one uppercase letter, one lowercase letter, and one number'
    )]
    public string $newPassword = '';
}
