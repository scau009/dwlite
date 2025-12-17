<?php

namespace App\Entity;

use App\Repository\MerchantProductRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 商户选品 - 商户从平台商品库中选择要售卖的商品
 */
#[ORM\Entity(repositoryClass: MerchantProductRepository::class)]
#[ORM\Table(name: 'merchant_products')]
#[ORM\UniqueConstraint(name: 'uniq_merchant_product_sku', columns: ['merchant_id', 'product_sku_id'])]
#[ORM\Index(name: 'idx_merchant_product_merchant', columns: ['merchant_id'])]
#[ORM\Index(name: 'idx_merchant_product_product', columns: ['product_id'])]
#[ORM\Index(name: 'idx_merchant_product_status', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class MerchantProduct
{
    // 状态
    public const STATUS_ACTIVE = 'active';       // 正常售卖
    public const STATUS_DISABLED = 'disabled';   // 已下架
    public const STATUS_OUT_OF_STOCK = 'out_of_stock'; // 缺货

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Merchant::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Merchant $merchant;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\ManyToOne(targetEntity: ProductSku::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ProductSku $productSku;

    // 商户自定义售价（可选，不填则用平台建议价）
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $sellingPrice = null;

    // 商户成本价（用于利润计算）
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $costPrice = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_ACTIVE;

    // 选品时间
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $selectedAt;

    // 首次上架时间
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $firstListedAt = null;

    // 商户备注
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->selectedAt = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getMerchant(): Merchant
    {
        return $this->merchant;
    }

    public function setMerchant(Merchant $merchant): static
    {
        $this->merchant = $merchant;
        return $this;
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

    public function getProductSku(): ProductSku
    {
        return $this->productSku;
    }

    public function setProductSku(ProductSku $productSku): static
    {
        $this->productSku = $productSku;
        return $this;
    }

    public function getSellingPrice(): ?string
    {
        return $this->sellingPrice;
    }

    public function setSellingPrice(?string $sellingPrice): static
    {
        $this->sellingPrice = $sellingPrice;
        return $this;
    }

    public function getCostPrice(): ?string
    {
        return $this->costPrice;
    }

    public function setCostPrice(?string $costPrice): static
    {
        $this->costPrice = $costPrice;
        return $this;
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

    public function getSelectedAt(): \DateTimeImmutable
    {
        return $this->selectedAt;
    }

    public function getFirstListedAt(): ?\DateTimeImmutable
    {
        return $this->firstListedAt;
    }

    public function setFirstListedAt(?\DateTimeImmutable $firstListedAt): static
    {
        $this->firstListedAt = $firstListedAt;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
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

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isDisabled(): bool
    {
        return $this->status === self::STATUS_DISABLED;
    }

    /**
     * 获取实际售价（商户自定义价或平台建议价）
     */
    public function getEffectiveSellingPrice(): ?string
    {
        return $this->sellingPrice ?? $this->productSku->getSellingPrice();
    }

    /**
     * 计算利润（售价 - 成本）
     */
    public function getProfit(): ?string
    {
        $sellingPrice = $this->getEffectiveSellingPrice();
        if ($sellingPrice === null || $this->costPrice === null) {
            return null;
        }
        return bcsub($sellingPrice, $this->costPrice, 2);
    }
}