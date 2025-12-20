<?php

namespace App\Entity;

use App\Enum\SizeUnit;
use App\Repository\ProductSkuRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: ProductSkuRepository::class)]
#[ORM\Table(name: 'product_skus')]
#[ORM\Index(name: 'idx_sku_product', columns: ['product_id'])]
#[ORM\Index(name: 'idx_sku_active', columns: ['is_active'])]
#[ORM\HasLifecycleCallbacks]
class ProductSku
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'skus')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(name: 'size_unit', type: 'string', length: 20, nullable: true, enumType: SizeUnit::class)]
    private ?SizeUnit $sizeUnit = null;  // 尺码单位：EU、US、UK、CM

    #[ORM\Column(name: 'size_value', length: 20, nullable: true)]
    private ?string $sizeValue = null;  // 尺码值，如：S、M、L、38、39、40

    #[ORM\Column(name: 'spec_info', type: 'json', nullable: true)]
    private ?array $specInfo = null;  // 规格摘要，如：{"颜色": "红色", "尺码": "S"}

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $price;  // 参考价

    #[ORM\Column(name: 'original_price', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $originalPrice = null;  // 发售价

    #[ORM\Column(name: 'is_active', type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): static
    {
        $this->product = $product;
        return $this;
    }

    public function getSizeUnit(): ?SizeUnit
    {
        return $this->sizeUnit;
    }

    public function setSizeUnit(?SizeUnit $sizeUnit): static
    {
        $this->sizeUnit = $sizeUnit;
        return $this;
    }

    public function getSizeValue(): ?string
    {
        return $this->sizeValue;
    }

    public function setSizeValue(?string $sizeValue): static
    {
        $this->sizeValue = $sizeValue;
        return $this;
    }

    public function getSpecInfo(): ?array
    {
        return $this->specInfo;
    }

    public function setSpecInfo(?array $specInfo): static
    {
        $this->specInfo = $specInfo;
        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getOriginalPrice(): ?string
    {
        return $this->originalPrice;
    }

    public function setOriginalPrice(?string $originalPrice): static
    {
        $this->originalPrice = $originalPrice;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
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
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * 获取规格描述
     */
    public function getSpecDescription(): string
    {
        $parts = [];
        if ($this->sizeUnit) {
            $parts[] = $this->sizeUnit->value;
        }
        if ($this->sizeValue) {
            $parts[] = $this->sizeValue;
        }
        return implode(' ', $parts);
    }
}
