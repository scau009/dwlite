<?php

namespace App\Entity;

use App\Repository\OutboundOrderItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 出库单明细 - 出库单中的商品明细
 */
#[ORM\Entity(repositoryClass: OutboundOrderItemRepository::class)]
#[ORM\Table(name: 'outbound_order_items')]
#[ORM\Index(name: 'idx_outbound_item_outbound', columns: ['outbound_order_id'])]
#[ORM\Index(name: 'idx_outbound_item_merchant', columns: ['merchant_id'])]
#[ORM\Index(name: 'idx_outbound_item_warehouse', columns: ['warehouse_id'])]
#[ORM\HasLifecycleCallbacks]
class OutboundOrderItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: OutboundOrder::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'outbound_order_id', nullable: false, onDelete: 'CASCADE')]
    private OutboundOrder $outboundOrder;

    // 商户和仓库快照（保留关联，nullable）
    #[ORM\ManyToOne(targetEntity: Merchant::class)]
    #[ORM\JoinColumn(name: 'merchant_id', nullable: true)]
    private ?Merchant $merchant = null;

    #[ORM\ManyToOne(targetEntity: Warehouse::class)]
    #[ORM\JoinColumn(name: 'warehouse_id', nullable: true)]
    private ?Warehouse $warehouse = null;

    // SKU 快照字段（保留出库时的商品信息）
    #[ORM\Column(name: 'sku_code', length: 50, nullable: true)]
    private ?string $skuCode = null;

    #[ORM\Column(name: 'color_code', length: 20, nullable: true)]
    private ?string $colorCode = null;

    #[ORM\Column(name: 'size_value', length: 20, nullable: true)]
    private ?string $sizeValue = null;

    #[ORM\Column(name: 'spec_info', type: 'json', nullable: true)]
    private ?array $specInfo = null;

    #[ORM\Column(name: 'product_name', length: 255, nullable: true)]
    private ?string $productName = null;

    #[ORM\Column(name: 'product_image', length: 500, nullable: true)]
    private ?string $productImage = null;

    // 数量
    #[ORM\Column(type: 'integer')]
    private int $quantity;

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

    public function getOutboundOrder(): OutboundOrder
    {
        return $this->outboundOrder;
    }

    public function setOutboundOrder(OutboundOrder $outboundOrder): static
    {
        $this->outboundOrder = $outboundOrder;
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

    public function getWarehouse(): ?Warehouse
    {
        return $this->warehouse;
    }

    public function setWarehouse(?Warehouse $warehouse): static
    {
        $this->warehouse = $warehouse;
        return $this;
    }

    // SKU 快照字段 getter/setter

    public function getSkuCode(): ?string
    {
        return $this->skuCode;
    }

    public function setSkuCode(?string $skuCode): static
    {
        $this->skuCode = $skuCode;
        return $this;
    }

    public function getColorCode(): ?string
    {
        return $this->colorCode;
    }

    public function setColorCode(?string $colorCode): static
    {
        $this->colorCode = $colorCode;
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

    public function getProductName(): ?string
    {
        return $this->productName;
    }

    public function setProductName(?string $productName): static
    {
        $this->productName = $productName;
        return $this;
    }

    public function getProductImage(): ?string
    {
        return $this->productImage;
    }

    public function setProductImage(?string $productImage): static
    {
        $this->productImage = $productImage;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
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
     * 从 SKU 快照关键信息
     */
    public function snapshotFromSku(ProductSku $sku): void
    {
        $product = $sku->getProduct();

        $this->skuCode = $sku->getSkuCode();
        $this->colorCode = $sku->getColorCode();
        $this->sizeValue = $sku->getSizeValue();
        $this->specInfo = $sku->getSpecInfo();
        $this->productName = $product->getName();

        $primaryImage = $product->getPrimaryImage();
        if ($primaryImage !== null) {
            $this->productImage = $primaryImage->getUrl();
        }
    }
}
