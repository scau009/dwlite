<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class CreateApiKeyRequest
{
    #[Assert\NotBlank(message: 'validation.name_required')]
    #[Assert\Length(max: 100, maxMessage: 'validation.name_max_length')]
    public string $name = '';

    #[Assert\NotBlank(message: 'validation.type_required')]
    #[Assert\Choice(choices: ['warehouse', 'merchant'], message: 'validation.type_invalid')]
    public string $type = '';

    /**
     * Warehouse ID (required if type = warehouse).
     */
    public ?string $warehouseId = null;

    /**
     * Merchant ID (required if type = merchant).
     */
    public ?string $merchantId = null;

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
