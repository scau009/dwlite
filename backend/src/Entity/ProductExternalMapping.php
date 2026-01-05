<?php

namespace App\Entity;

use App\Repository\ProductExternalMappingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: ProductExternalMappingRepository::class)]
#[ORM\Table(name: 'product_external_mappings')]
#[ORM\UniqueConstraint(name: 'uk_product_provider', columns: ['product_id', 'provider'])]
#[ORM\UniqueConstraint(name: 'uk_provider_external', columns: ['provider', 'external_id'])]
#[ORM\Index(name: 'idx_mapping_provider', columns: ['provider'])]
#[ORM\Index(name: 'idx_mapping_style_id', columns: ['provider', 'external_style_id'])]
#[ORM\HasLifecycleCallbacks]
class ProductExternalMapping
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(length: 50)]
    private string $provider;

    #[ORM\Column(name: 'external_id', length: 100)]
    private string $externalId;

    #[ORM\Column(name: 'external_style_id', length: 100, nullable: true)]
    private ?string $externalStyleId = null;

    #[ORM\Column(name: 'external_url', length: 500, nullable: true)]
    private ?string $externalUrl = null;

    #[ORM\Column(name: 'external_data', type: 'json', nullable: true)]
    private ?array $externalData = null;

    #[ORM\Column(name: 'last_synced_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastSyncedAt;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Product $product, string $provider, string $externalId)
    {
        $this->id = (string) new Ulid();
        $this->product = $product;
        $this->provider = $provider;
        $this->externalId = $externalId;
        $this->lastSyncedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getExternalStyleId(): ?string
    {
        return $this->externalStyleId;
    }

    public function setExternalStyleId(?string $externalStyleId): static
    {
        $this->externalStyleId = $externalStyleId;

        return $this;
    }

    public function getExternalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function setExternalUrl(?string $externalUrl): static
    {
        $this->externalUrl = $externalUrl;

        return $this;
    }

    public function getExternalData(): ?array
    {
        return $this->externalData;
    }

    public function setExternalData(?array $externalData): static
    {
        $this->externalData = $externalData;

        return $this;
    }

    public function getLastSyncedAt(): \DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function updateLastSyncedAt(): static
    {
        $this->lastSyncedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

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
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
