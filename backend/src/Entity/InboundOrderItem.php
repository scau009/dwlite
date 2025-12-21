<?php

namespace App\Entity;

use App\Repository\InboundOrderItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 送仓单明细 - 送仓单中的 SKU 明细
 */
#[ORM\Entity(repositoryClass: InboundOrderItemRepository::class)]
#[ORM\Table(name: 'inbound_order_items')]
#[ORM\Index(name: 'idx_inbound_item_order', columns: ['inbound_order_id'])]
#[ORM\Index(name: 'idx_inbound_item_sku', columns: ['product_sku_id'])]
#[ORM\UniqueConstraint(name: 'uniq_inbound_order_sku', columns: ['inbound_order_id', 'product_sku_id'])]
#[ORM\HasLifecycleCallbacks]
class InboundOrderItem
{
    // 明细状态
    public const STATUS_PENDING = 'pending';           // 待收货
    public const STATUS_RECEIVED = 'received';         // 已收货（数量一致）
    public const STATUS_PARTIAL = 'partial';           // 部分收货（数量不足）
    public const STATUS_OVER = 'over';                 // 超量收货
    public const STATUS_MISSING = 'missing';           // 未收到

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: InboundOrder::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private InboundOrder $inboundOrder;

    #[ORM\ManyToOne(targetEntity: ProductSku::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ProductSku $productSku = null;

    // SKU 快照字段（保留送仓时的商品信息）
    #[ORM\Column(name: 'sku_name', length: 255, nullable: true)]
    private ?string $skuName = null;

    #[ORM\Column(name: 'color_name', length: 255, nullable: true)]
    private ?string $colorName = null;

    #[ORM\Column(name: 'product_name', length: 255, nullable: true)]
    private ?string $productName = null;

    #[ORM\Column(name: 'product_image', length: 500, nullable: true)]
    private ?string $productImage = null;

    // 数量信息
    #[ORM\Column(type: 'integer')]
    private int $expectedQuantity;  // 预报数量

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $receivedQuantity = 0;  // 实收数量

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $damagedQuantity = 0;  // 损坏数量（含在实收内）

    // 成本信息（入库成本）
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $unitCost = null;  // 单件成本

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    // 仓库反馈
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $warehouseRemark = null;  // 仓库备注（如：包装破损等）

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $receivedAt = null;  // 收货时间

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

    public function getInboundOrder(): InboundOrder
    {
        return $this->inboundOrder;
    }

    public function setInboundOrder(InboundOrder $inboundOrder): static
    {
        $this->inboundOrder = $inboundOrder;
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

    /**
     * 从 SKU 快照关键信息
     */
    public function snapshotFromSku(ProductSku $sku): void
    {
        $product = $sku->getProduct();

        $this->skuName = $sku->getSkuName();
        $this->colorName = $product->getColor();
        $this->productName = $product->getName();

        $primaryImage = $product->getPrimaryImage();
        if ($primaryImage !== null) {
            $this->productImage = $primaryImage->getUrl();
        }
    }

    public function getExpectedQuantity(): int
    {
        return $this->expectedQuantity;
    }

    public function setExpectedQuantity(int $expectedQuantity): static
    {
        $this->expectedQuantity = $expectedQuantity;
        return $this;
    }

    public function getReceivedQuantity(): int
    {
        return $this->receivedQuantity;
    }

    public function setReceivedQuantity(int $receivedQuantity): static
    {
        $this->receivedQuantity = $receivedQuantity;
        return $this;
    }

    public function getDamagedQuantity(): int
    {
        return $this->damagedQuantity;
    }

    public function setDamagedQuantity(int $damagedQuantity): static
    {
        $this->damagedQuantity = $damagedQuantity;
        return $this;
    }

    public function getUnitCost(): ?string
    {
        return $this->unitCost;
    }

    public function setUnitCost(?string $unitCost): static
    {
        $this->unitCost = $unitCost;
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

    public function getWarehouseRemark(): ?string
    {
        return $this->warehouseRemark;
    }

    public function setWarehouseRemark(?string $warehouseRemark): static
    {
        $this->warehouseRemark = $warehouseRemark;
        return $this;
    }

    public function getReceivedAt(): ?\DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(?\DateTimeImmutable $receivedAt): static
    {
        $this->receivedAt = $receivedAt;
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

    /**
     * 获取数量差异（预报 - 实收）
     */
    public function getQuantityDifference(): int
    {
        return $this->expectedQuantity - $this->receivedQuantity;
    }

    /**
     * 是否有差异
     */
    public function hasDifference(): bool
    {
        return $this->getQuantityDifference() !== 0;
    }

    /**
     * 获取可用数量（实收 - 损坏）
     */
    public function getAvailableQuantity(): int
    {
        return $this->receivedQuantity - $this->damagedQuantity;
    }

    /**
     * 计算总成本
     */
    public function getTotalCost(): ?string
    {
        if ($this->unitCost === null) {
            return null;
        }
        return bcmul($this->unitCost, (string) $this->receivedQuantity, 2);
    }

    /**
     * 确认收货
     */
    public function confirmReceived(int $receivedQty, int $damagedQty = 0, ?string $remark = null): void
    {
        $this->receivedQuantity = $receivedQty;
        $this->damagedQuantity = $damagedQty;
        $this->warehouseRemark = $remark;
        $this->receivedAt = new \DateTimeImmutable();

        // 更新状态
        if ($receivedQty === 0) {
            $this->status = self::STATUS_MISSING;
        } elseif ($receivedQty < $this->expectedQuantity) {
            $this->status = self::STATUS_PARTIAL;
        } elseif ($receivedQty > $this->expectedQuantity) {
            $this->status = self::STATUS_OVER;
        } else {
            $this->status = self::STATUS_RECEIVED;
        }
    }
}
