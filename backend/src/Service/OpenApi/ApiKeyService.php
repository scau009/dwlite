<?php

namespace App\Service\OpenApi;

use App\Entity\ApiKey;
use App\Entity\Merchant;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Repository\ApiKeyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ApiKeyService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ApiKeyRepository $apiKeyRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create a new API Key for a warehouse.
     *
     * @param array<string> $permissions
     * @param array<string>|null $ipWhitelist
     *
     * @return array{apiKey: ApiKey, plainSecret: string}
     */
    public function createWarehouseApiKey(
        Warehouse $warehouse,
        string $name,
        array $permissions,
        ?User $createdBy = null,
        ?\DateTimeImmutable $expiresAt = null,
        ?array $ipWhitelist = null
    ): array {
        $apiKey = new ApiKey();
        $keyId = ApiKey::generateKeyId();
        $plainSecret = ApiKey::generateKeySecret();

        $apiKey->setKeyId($keyId)
            ->setKeySecret($plainSecret)
            ->setName($name)
            ->setType(ApiKey::TYPE_WAREHOUSE)
            ->setWarehouse($warehouse)
            ->setPermissions($permissions)
            ->setExpiresAt($expiresAt)
            ->setIpWhitelist($ipWhitelist)
            ->setCreatedBy($createdBy);

        $this->entityManager->persist($apiKey);
        $this->entityManager->flush();

        $this->logger->info('Created warehouse API key', [
            'apiKeyId' => $apiKey->getId(),
            'keyId' => $keyId,
            'warehouseId' => $warehouse->getId(),
            'createdBy' => $createdBy?->getId(),
        ]);

        return [
            'apiKey' => $apiKey,
            'plainSecret' => $plainSecret,
        ];
    }

    /**
     * Create a new API Key for a merchant.
     *
     * @param array<string> $permissions
     * @param array<string>|null $ipWhitelist
     *
     * @return array{apiKey: ApiKey, plainSecret: string}
     */
    public function createMerchantApiKey(
        Merchant $merchant,
        string $name,
        array $permissions,
        ?User $createdBy = null,
        ?\DateTimeImmutable $expiresAt = null,
        ?array $ipWhitelist = null
    ): array {
        $apiKey = new ApiKey();
        $keyId = ApiKey::generateKeyId();
        $plainSecret = ApiKey::generateKeySecret();

        $apiKey->setKeyId($keyId)
            ->setKeySecret($plainSecret)
            ->setName($name)
            ->setType(ApiKey::TYPE_MERCHANT)
            ->setMerchant($merchant)
            ->setPermissions($permissions)
            ->setExpiresAt($expiresAt)
            ->setIpWhitelist($ipWhitelist)
            ->setCreatedBy($createdBy);

        $this->entityManager->persist($apiKey);
        $this->entityManager->flush();

        $this->logger->info('Created merchant API key', [
            'apiKeyId' => $apiKey->getId(),
            'keyId' => $keyId,
            'merchantId' => $merchant->getId(),
            'createdBy' => $createdBy?->getId(),
        ]);

        return [
            'apiKey' => $apiKey,
            'plainSecret' => $plainSecret,
        ];
    }

    /**
     * Validate an API Key by key ID and secret.
     */
    public function validateApiKey(string $keyId, string $plainSecret): ?ApiKey
    {
        $apiKey = $this->apiKeyRepository->findByKeyId($keyId);

        if ($apiKey === null) {
            $this->logger->warning('API key not found', ['keyId' => $keyId]);

            return null;
        }

        // Check status
        if (!$apiKey->isActive()) {
            $this->logger->warning('API key not active', [
                'keyId' => $keyId,
                'status' => $apiKey->getStatus(),
            ]);

            return null;
        }

        // Check expiration
        if ($apiKey->isExpired()) {
            $this->logger->warning('API key expired', [
                'keyId' => $keyId,
                'expiresAt' => $apiKey->getExpiresAt()?->format(\DateTimeInterface::ATOM),
            ]);

            return null;
        }

        // Verify secret
        if (!$apiKey->verifySecret($plainSecret)) {
            $this->logger->warning('API key secret verification failed', ['keyId' => $keyId]);

            return null;
        }

        // Update last used timestamp
        $apiKey->updateLastUsedAt();
        $this->entityManager->flush();

        return $apiKey;
    }

    /**
     * Suspend an API Key.
     */
    public function suspendApiKey(ApiKey $apiKey): void
    {
        $apiKey->suspend();
        $this->entityManager->flush();

        $this->logger->info('Suspended API key', [
            'apiKeyId' => $apiKey->getId(),
            'keyId' => $apiKey->getKeyId(),
        ]);
    }

    /**
     * Activate a suspended API Key.
     */
    public function activateApiKey(ApiKey $apiKey): void
    {
        $apiKey->activate();
        $this->entityManager->flush();

        $this->logger->info('Activated API key', [
            'apiKeyId' => $apiKey->getId(),
            'keyId' => $apiKey->getKeyId(),
        ]);
    }

    /**
     * Revoke an API Key permanently.
     */
    public function revokeApiKey(ApiKey $apiKey): void
    {
        $apiKey->revoke();
        $this->entityManager->flush();

        $this->logger->info('Revoked API key', [
            'apiKeyId' => $apiKey->getId(),
            'keyId' => $apiKey->getKeyId(),
        ]);
    }

    /**
     * Delete an API Key.
     */
    public function deleteApiKey(ApiKey $apiKey): void
    {
        $keyId = $apiKey->getKeyId();
        $this->entityManager->remove($apiKey);
        $this->entityManager->flush();

        $this->logger->info('Deleted API key', ['keyId' => $keyId]);
    }

    /**
     * Update API Key permissions.
     *
     * @param array<string> $permissions
     */
    public function updatePermissions(ApiKey $apiKey, array $permissions): void
    {
        $apiKey->setPermissions($permissions);
        $this->entityManager->flush();

        $this->logger->info('Updated API key permissions', [
            'apiKeyId' => $apiKey->getId(),
            'keyId' => $apiKey->getKeyId(),
            'permissions' => $permissions,
        ]);
    }

    /**
     * Update API Key IP whitelist.
     *
     * @param array<string>|null $ipWhitelist
     */
    public function updateIpWhitelist(ApiKey $apiKey, ?array $ipWhitelist): void
    {
        $apiKey->setIpWhitelist($ipWhitelist);
        $this->entityManager->flush();

        $this->logger->info('Updated API key IP whitelist', [
            'apiKeyId' => $apiKey->getId(),
            'keyId' => $apiKey->getKeyId(),
            'ipWhitelist' => $ipWhitelist,
        ]);
    }

    /**
     * Check if an API Key has a specific permission.
     */
    public function hasPermission(ApiKey $apiKey, string $permission): bool
    {
        return $apiKey->hasPermission($permission);
    }

    /**
     * Check if a client IP is allowed for an API Key.
     */
    public function isIpAllowed(ApiKey $apiKey, string $clientIp): bool
    {
        return $apiKey->isIpAllowed($clientIp);
    }

    /**
     * Get API Key by key ID.
     */
    public function getApiKeyByKeyId(string $keyId): ?ApiKey
    {
        return $this->apiKeyRepository->findByKeyId($keyId);
    }

    /**
     * Get active API Key by key ID.
     */
    public function getActiveApiKeyByKeyId(string $keyId): ?ApiKey
    {
        return $this->apiKeyRepository->findActiveByKeyId($keyId);
    }

    /**
     * Get the API Key for a merchant (only one allowed).
     */
    public function getMerchantApiKey(Merchant $merchant): ?ApiKey
    {
        return $this->apiKeyRepository->findOneBy([
            'merchant' => $merchant,
            'type' => ApiKey::TYPE_MERCHANT,
        ]);
    }

    /**
     * Count the number of API Keys for a merchant.
     */
    public function countByMerchant(Merchant $merchant): int
    {
        return $this->apiKeyRepository->count([
            'merchant' => $merchant,
            'type' => ApiKey::TYPE_MERCHANT,
        ]);
    }

    /**
     * Regenerate the secret for an API Key.
     *
     * @return string The new plain secret (show to user once)
     */
    public function regenerateSecret(ApiKey $apiKey): string
    {
        $plainSecret = ApiKey::generateKeySecret();
        $apiKey->setKeySecret($plainSecret);
        $this->entityManager->flush();

        $this->logger->info('Regenerated API key secret', [
            'apiKeyId' => $apiKey->getId(),
            'keyId' => $apiKey->getKeyId(),
        ]);

        return $plainSecret;
    }
}
