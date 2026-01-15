<?php

namespace App\Security;

use App\Entity\ApiKey;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * User wrapper for API Key authentication.
 * This allows API Keys to be used in the Symfony security system.
 */
class ApiKeyUser implements UserInterface
{
    public function __construct(
        private readonly ApiKey $apiKey
    ) {
    }

    public function getApiKey(): ApiKey
    {
        return $this->apiKey;
    }

    public function getRoles(): array
    {
        // Map API Key type to roles
        return match ($this->apiKey->getType()) {
            ApiKey::TYPE_WAREHOUSE => ['ROLE_WAREHOUSE_API'],
            ApiKey::TYPE_MERCHANT => ['ROLE_MERCHANT_API'],
            default => ['ROLE_API'],
        };
    }

    public function eraseCredentials(): void
    {
        // Nothing to erase
    }

    public function getUserIdentifier(): string
    {
        return $this->apiKey->getKeyId();
    }

    /**
     * Check if the API Key has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->apiKey->hasPermission($permission);
    }
}
