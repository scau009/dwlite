<?php

namespace App\Entity;

use App\Repository\OutboundOrderItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 出库单明细 - 出库单中的商品明细.
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

    #[ORM\ManyToOne(targetEntity: ProductSku::class)]
    #[ORM\JoinColumn(name: 'product_sku_id', nullable: true)]
    private ?ProductSku $productSku = null;

    // SKU 快照字段（保留出库时的商品信息）
    #[ORM\Column(name: 'sku_name', length: 255, nullable: true)]
    private ?string $skuName = null;

    #[ORM\Column(name: 'style_number', length: 255, nullable: true)]
    private ?string $styleNumber = null;

    #[ORM\Column(name: 'color_name', length: 255, nullable: true)]
    private ?string $colorName = null;

    #[ORM\Column(name: 'product_name', length: 255, nullable: true)]
    private ?string $productName = null;

    #[ORM\Column(name: 'product_image', length: 500, nullable: true)]
    private ?string $productImage = null;

    // 库存类型：normal（正常库存）或 damaged（破损库存）
    public const STOCK_TYPE_NORMAL = 'normal';
    public const STOCK_TYPE_DAMAGED = 'damaged';

    #[ORM\Column(name: 'stock_type', length: 20)]
    private string $stockType = self::STOCK_TYPE_NORMAL;

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
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
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

    public function getProductSku(): ?ProductSku
    {
        return $this->productSku;
    }

    public function setProductSku(?ProductSku $productSku): static
    {
        $this->productSku = $productSku;

        return $this;
    }

    // SKU 快照字段 getter/setter

    public function getSkuName(): ?string
    {
        return $this->skuName;
    }

    public function setSkuName(?string $skuName): static
    {
        $this->skuName = $skuName;

        return $this;
    }

    public function getStyleNumber(): ?string
    {
        return $this->styleNumber;
    }

    public function setStyleNumber(?string $styleNumber): static
    {
        $this->styleNumber = $styleNumber;

        return $this;
    }

    public function getColorName(): ?string
    {
        return $this->colorName;
    }

    public function setColorName(?string $colorName): static
    {
        $this->colorName = $colorName;

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

    public function getStockType(): string
    {
        return $this->stockType;
    }

    public function setStockType(string $stockType): static
    {
        $this->stockType = $stockType;

        return $this;
    }

    public function isNormalStock(): bool
    {
        return $this->stockType === self::STOCK_TYPE_NORMAL;
    }

    public function isDamagedStock(): bool
    {
        return $this->stockType === self::STOCK_TYPE_DAMAGED;
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
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * 从 SKU 快照关键信息.
     */
    public function snapshotFromSku(ProductSku $sku): void
    {
        $product = $sku->getProduct();

        $this->skuName = $sku->getSizeValue();
        $this->styleNumber = $product->getStyleNumber();
        $this->colorName = $product->getColor();
        $this->productName = $product->getName();

        $primaryImage = $product->getPrimaryImage();
        if ($primaryImage !== null) {
            $this->productImage = $primaryImage->getUrl();
        }
    }
}
