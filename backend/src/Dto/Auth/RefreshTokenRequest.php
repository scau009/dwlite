<?php

namespace App\Dto\Auth;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

class RefreshTokenRequest
{
    #[Assert\NotBlank(message: 'Refresh token is required')]
    #[SerializedName('refresh_token')]
    public string $refreshToken = '';
}
