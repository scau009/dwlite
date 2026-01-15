<?php

namespace App\Dto\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

class CreateMerchantApiKeyRequest
{
    #[Assert\NotBlank(message: 'validation.name_required')]
    #[Assert\Length(max: 100, maxMessage: 'validation.name_max_length')]
    public string $name = '';

    /**
     * Permissions array.
     *
     * @var array<string>
     */
    #[Assert\NotBlank(message: 'validation.permissions_required')]
    #[Assert\Type(type: 'array', message: 'validation.permissions_must_be_array')]
    public array $permissions = [];

    /**
     * IP whitelist (optional).
     *
     * @var array<string>|null
     */
    #[Assert\Type(type: 'array', message: 'validation.ip_whitelist_must_be_array')]
    public ?array $ipWhitelist = null;

    /**
     * Expiration date (optional).
     */
    public ?\DateTimeImmutable $expiresAt = null;
}
