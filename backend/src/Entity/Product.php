<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'products')]
#[ORM\Index(name: 'idx_product_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_product_style_number', columns: ['style_number'])]
#[ORM\Index(name: 'idx_product_season', columns: ['season'])]
#[ORM\Index(name: 'idx_product_brand', columns: ['brand_id'])]
#[ORM\Index(name: 'idx_product_category', columns: ['category_id'])]
#[ORM\Index(name: 'idx_product_active', columns: ['is_active'])]
#[ORM\HasLifecycleCallbacks]
class Product
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Brand::class, inversedBy: 'products')]
    #[ORM\JoinColumn(name: 'brand_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Brand $brand = null;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\Column(length: 200)]
    private string $name;

    #[ORM\Column(length: 220, unique: true)]
    private string $slug;

    #[ORM\Column(length: 50, name: 'style_number')]
    private string $styleNumber;  // 款号

    #[ORM\Column(length: 20)]
    private string $season;  // 季节：2024SS, 2024AW, 2024FW 等

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $color = null;  // 颜色名，如：红色、深蓝

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true], name: 'is_active')]
    private bool $isActive = true;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'products')]
    #[ORM\JoinTable(name: 'product_tags')]
    private Collection $tags;

    #[ORM\OneToMany(targetEntity: ProductSku::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $skus;

    #[ORM\OneToMany(targetEntity: ProductImage::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $images;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->tags = new ArrayCollection();
        $this->skus = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function setBrand(?Brand $brand): static
    {
        $this->brand = $brand;
        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;
        return $this;
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getStyleNumber(): string
    {
        return $this->styleNumber;
    }

    public function setStyleNumber(string $styleNumber): static
    {
        $this->styleNumber = $styleNumber;
        return $this;
    }

    public function getSeason(): string
    {
        return $this->season;
    }

    public function setSeason(string $season): static
    {
        $this->season = $season;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
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

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);
        return $this;
    }

    public function clearTags(): static
    {
        $this->tags->clear();
        return $this;
    }

    /**
     * @return Collection<int, ProductSku>
     */
    public function getSkus(): Collection
    {
        return $this->skus;
    }

    public function addSku(ProductSku $sku): static
    {
        if (!$this->skus->contains($sku)) {
            $this->skus->add($sku);
            $sku->setProduct($this);
        }
        return $this;
    }

    public function removeSku(ProductSku $sku): static
    {
        if ($this->skus->removeElement($sku)) {
            if ($sku->getProduct() === $this) {
                $sku->setProduct($this);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ProductImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ProductImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setProduct($this);
        }
        return $this;
    }

    public function removeImage(ProductImage $image): static
    {
        $this->images->removeElement($image);
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

    // 便捷方法

    public function getPrimaryImage(): ?ProductImage
    {
        foreach ($this->images as $image) {
            if ($image->isPrimary()) {
                return $image;
            }
        }
        return $this->images->first() ?: null;
    }

    /**
     * 获取价格区间（最低价 - 最高价）
     */
    public function getPriceRange(): array
    {
        $prices = $this->skus
            ->filter(fn($sku) => $sku->isActive())
            ->map(fn($sku) => (float) $sku->getPrice())
            ->toArray();

        if (empty($prices)) {
            return ['min' => null, 'max' => null];
        }

        return ['min' => min($prices), 'max' => max($prices)];
    }

    /**
     * 获取 SKU 数量
     */
    public function getSkuCount(): int
    {
        return $this->skus->filter(fn($sku) => $sku->isActive())->count();
    }
}
