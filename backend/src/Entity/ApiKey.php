<?php

namespace App\Entity;

use App\Repository\ApiKeyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: ApiKeyRepository::class)]
#[ORM\Table(name: 'api_keys')]
#[ORM\HasLifecycleCallbacks]
class ApiKey
{
    // API Key 类型
    public const TYPE_WAREHOUSE = 'warehouse';
    public const TYPE_MERCHANT = 'merchant';

    // API Key 状态
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_REVOKED = 'revoked';

    // 权限常量 - 仓库
    public const PERM_INBOUND_READ = 'inbound:read';
    public const PERM_INBOUND_WRITE = 'inbound:write';
    public const PERM_OUTBOUND_READ = 'outbound:read';
    public const PERM_OUTBOUND_WRITE = 'outbound:write';
    public const PERM_INVENTORY_READ = 'inventory:read';
    public const PERM_INVENTORY_WRITE = 'inventory:write';

    // 权限常量 - 商户
    public const PERM_MERCHANT_INVENTORY_READ = 'merchant_inventory:read';
    public const PERM_MERCHANT_INVENTORY_WRITE = 'merchant_inventory:write';
    public const PERM_MERCHANT_INBOUND_READ = 'merchant_inbound:read';
    public const PERM_MERCHANT_INBOUND_WRITE = 'merchant_inbound:write';
    public const PERM_FULFILLMENT_READ = 'fulfillment:read';
    public const PERM_FULFILLMENT_WRITE = 'fulfillment:write';
    public const PERM_SETTLEMENT_READ = 'settlement:read';
    public const PERM_LISTING_READ = 'listing:read';
    public const PERM_LISTING_WRITE = 'listing:write';
    public const PERM_WEBHOOK_MANAGE = 'webhook:manage';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(type: 'string', length: 32, unique: true)]
    private string $keyId;

    #[ORM\Column(type: 'string', length: 64)]
    private string $keySecret;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(type: 'string', length: 20)]
    private string $type;

    #[ORM\ManyToOne(targetEntity: Warehouse::class)]
    #[ORM\JoinColumn(name: 'warehouse_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Warehouse $warehouse = null;

    #[ORM\ManyToOne(targetEntity: Merchant::class)]
    #[ORM\JoinColumn(name: 'merchant_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Merchant $merchant = null;

    /** @var array<string> */
    #[ORM\Column(type: 'json')]
    private array $permissions = [];

    /** @var array<string>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $ipWhitelist = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Webhook> */
    #[ORM\OneToMany(targetEntity: Webhook::class, mappedBy: 'apiKey', cascade: ['persist', 'remove'])]
    private Collection $webhooks;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->webhooks = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * Generate a new API Key ID (public identifier).
     */
    public static function generateKeyId(): string
    {
        return 'dwl_' . bin2hex(random_bytes(14));
    }

    /**
     * Generate a new API Secret (to be shown once and stored hashed).
     */
    public static function generateKeySecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getKeyId(): string
    {
        return $this->keyId;
    }

    public function setKeyId(string $keyId): static
    {
        $this->keyId = $keyId;
        return $this;
    }

    public function getKeySecret(): string
    {
        return $this->keySecret;
    }

    public function setKeySecret(string $keySecret): static
    {
        $this->keySecret = $keySecret;
        return $this;
    }

    /**
     * Verify a plain secret against the stored secret.
     */
    public function verifySecret(string $plainSecret): bool
    {
        return hash_equals($this->keySecret, $plainSecret);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function isWarehouseType(): bool
    {
        return $this->type === self::TYPE_WAREHOUSE;
    }

    public function isMerchantType(): bool
    {
        return $this->type === self::TYPE_MERCHANT;
    }

    public function getWarehouse(): ?Warehouse
    {
        return $this->warehouse;
    }

    public function setWarehouse(?Warehouse $warehouse): static
    {
        $this->warehouse = $warehouse;
        return $this;
    }

    public function getMerchant(): ?Merchant
    {
        return $this->merchant;
    }

    public function setMerchant(?Merchant $merchant): static
    {
        $this->merchant = $merchant;
        return $this;
    }

    /**
     * @return array<string>
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * @param array<string> $permissions
     */
    public function setPermissions(array $permissions): static
    {
        $this->permissions = $permissions;
        return $this;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function addPermission(string $permission): static
    {
        if (!in_array($permission, $this->permissions, true)) {
            $this->permissions[] = $permission;
        }
        return $this;
    }

    public function removePermission(string $permission): static
    {
        $this->permissions = array_values(array_filter(
            $this->permissions,
            fn($p) => $p !== $permission
        ));
        return $this;
    }

    /**
     * @return array<string>|null
     */
    public function getIpWhitelist(): ?array
    {
        return $this->ipWhitelist;
    }

    /**
     * @param array<string>|null $ipWhitelist
     */
    public function setIpWhitelist(?array $ipWhitelist): static
    {
        $this->ipWhitelist = $ipWhitelist;
        return $this;
    }

    public function isIpAllowed(string $ip): bool
    {
        if ($this->ipWhitelist === null || count($this->ipWhitelist) === 0) {
            return true;
        }
        return in_array($ip, $this->ipWhitelist, true);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }

    public function suspend(): static
    {
        $this->status = self::STATUS_SUSPENDED;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return $this;
    }

    public function activate(): static
    {
        $this->status = self::STATUS_ACTIVE;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return $this;
    }

    public function revoke(): static
    {
        $this->status = self::STATUS_REVOKED;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return $this;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): static
    {
        $this->lastUsedAt = $lastUsedAt;
        return $this;
    }

    public function updateLastUsedAt(): static
    {
        $this->lastUsedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }
        return $this->expiresAt < new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * @return Collection<int, Webhook>
     */
    public function getWebhooks(): Collection
    {
        return $this->webhooks;
    }

    public function addWebhook(Webhook $webhook): static
    {
        if (!$this->webhooks->contains($webhook)) {
            $this->webhooks->add($webhook);
            $webhook->setApiKey($this);
        }
        return $this;
    }

    public function removeWebhook(Webhook $webhook): static
    {
        $this->webhooks->removeElement($webhook);
        return $this;
    }

    /**
     * Get default permissions for warehouse type.
     *
     * @return array<string>
     */
    public static function getDefaultWarehousePermissions(): array
    {
        return [
            self::PERM_INBOUND_READ,
            self::PERM_INBOUND_WRITE,
            self::PERM_OUTBOUND_READ,
            self::PERM_OUTBOUND_WRITE,
            self::PERM_INVENTORY_READ,
            self::PERM_INVENTORY_WRITE,
            self::PERM_WEBHOOK_MANAGE,
        ];
    }

    /**
     * Get default permissions for merchant type.
     *
     * @return array<string>
     */
    public static function getDefaultMerchantPermissions(): array
    {
        return [
            self::PERM_MERCHANT_INVENTORY_READ,
            self::PERM_MERCHANT_INVENTORY_WRITE,
            self::PERM_MERCHANT_INBOUND_READ,
            self::PERM_MERCHANT_INBOUND_WRITE,
            self::PERM_FULFILLMENT_READ,
            self::PERM_FULFILLMENT_WRITE,
            self::PERM_SETTLEMENT_READ,
            self::PERM_LISTING_READ,
            self::PERM_LISTING_WRITE,
            self::PERM_WEBHOOK_MANAGE,
        ];
    }
}
