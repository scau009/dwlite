<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 订单明细 - 订单中的商品明细
 */
#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\Table(name: 'order_items')]
#[ORM\Index(name: 'idx_order_item_order', columns: ['order_id'])]
#[ORM\Index(name: 'idx_order_item_sku', columns: ['product_sku_id'])]
#[ORM\Index(name: 'idx_order_item_channel_product', columns: ['channel_product_id'])]
#[ORM\HasLifecycleCallbacks]
class OrderItem
{
    // 分配状态
    public const ALLOCATION_PENDING = 'pending';     // 待分配
    public const ALLOCATION_PARTIAL = 'partial';     // 部分分配
    public const ALLOCATION_FULL = 'full';           // 全部分配
    public const ALLOCATION_FAILED = 'failed';       // 分配失败

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\ManyToOne(targetEntity: ProductSku::class)]
    #[ORM\JoinColumn(name: 'product_sku_id', nullable: false)]
    private ProductSku $productSku;

    // 渠道商品（用于追溯定价来源）
    #[ORM\ManyToOne(targetEntity: ChannelProduct::class)]
    #[ORM\JoinColumn(name: 'channel_product_id', nullable: true)]
    private ?ChannelProduct $channelProduct = null;

    // 外部商品信息（从销售渠道同步的原始信息）
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $externalProductId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $externalProductName = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $externalProductImage = null;

    // 数量
    #[ORM\Column(type: 'integer')]
    private int $quantity;  // 购买数量

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $allocatedQuantity = 0;  // 已分配数量

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $shippedQuantity = 0;  // 已发货数量

    // 价格
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $unitPrice;  // 单价

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $totalPrice;  // 总价（单价 * 数量）

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $discountAmount = '0.00';  // 优惠金额

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $payableAmount;  // 应付金额（总价 - 优惠）

    // 分配状态
    #[ORM\Column(type: 'string', length: 20)]
    private string $allocationStatus = self::ALLOCATION_PENDING;

    // 备注
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $remark = null;

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

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function setOrder(Order $order): static
    {
        $this->order = $order;
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

    public function getChannelProduct(): ?ChannelProduct
    {
        return $this->channelProduct;
    }

    public function setChannelProduct(?ChannelProduct $channelProduct): static
    {
        $this->channelProduct = $channelProduct;
        return $this;
    }

    public function getExternalProductId(): ?string
    {
        return $this->externalProductId;
    }

    public function setExternalProductId(?string $externalProductId): static
    {
        $this->externalProductId = $externalProductId;
        return $this;
    }

    public function getExternalProductName(): ?string
    {
        return $this->externalProductName;
    }

    public function setExternalProductName(?string $externalProductName): static
    {
        $this->externalProductName = $externalProductName;
        return $this;
    }

    public function getExternalProductImage(): ?string
    {
        return $this->externalProductImage;
    }

    public function setExternalProductImage(?string $externalProductImage): static
    {
        $this->externalProductImage = $externalProductImage;
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

    public function getAllocatedQuantity(): int
    {
        return $this->allocatedQuantity;
    }

    public function setAllocatedQuantity(int $allocatedQuantity): static
    {
        $this->allocatedQuantity = $allocatedQuantity;
        $this->updateAllocationStatus();
        return $this;
    }

    public function getShippedQuantity(): int
    {
        return $this->shippedQuantity;
    }

    public function setShippedQuantity(int $shippedQuantity): static
    {
        $this->shippedQuantity = $shippedQuantity;
        return $this;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;
        return $this;
    }

    public function getTotalPrice(): string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(string $totalPrice): static
    {
        $this->totalPrice = $totalPrice;
        return $this;
    }

    public function getDiscountAmount(): string
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(string $discountAmount): static
    {
        $this->discountAmount = $discountAmount;
        return $this;
    }

    public function getPayableAmount(): string
    {
        return $this->payableAmount;
    }

    public function setPayableAmount(string $payableAmount): static
    {
        $this->payableAmount = $payableAmount;
        return $this;
    }

    public function getAllocationStatus(): string
    {
        return $this->allocationStatus;
    }

    public function setAllocationStatus(string $allocationStatus): static
    {
        $this->allocationStatus = $allocationStatus;
        return $this;
    }

    public function getRemark(): ?string
    {
        return $this->remark;
    }

    public function setRemark(?string $remark): static
    {
        $this->remark = $remark;
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
     * 获取待分配数量
     */
    public function getPendingQuantity(): int
    {
        return max(0, $this->quantity - $this->allocatedQuantity);
    }

    /**
     * 是否已全部分配
     */
    public function isFullyAllocated(): bool
    {
        return $this->allocatedQuantity >= $this->quantity;
    }

    /**
     * 是否部分分配
     */
    public function isPartiallyAllocated(): bool
    {
        return $this->allocatedQuantity > 0 && $this->allocatedQuantity < $this->quantity;
    }

    /**
     * 是否已全部发货
     */
    public function isFullyShipped(): bool
    {
        return $this->shippedQuantity >= $this->quantity;
    }

    /**
     * 增加已分配数量
     */
    public function addAllocatedQuantity(int $quantity): void
    {
        $this->allocatedQuantity += $quantity;
        $this->updateAllocationStatus();
    }

    /**
     * 增加已发货数量
     */
    public function addShippedQuantity(int $quantity): void
    {
        $this->shippedQuantity += $quantity;
    }

    /**
     * 更新分配状态
     */
    private function updateAllocationStatus(): void
    {
        if ($this->allocatedQuantity >= $this->quantity) {
            $this->allocationStatus = self::ALLOCATION_FULL;
        } elseif ($this->allocatedQuantity > 0) {
            $this->allocationStatus = self::ALLOCATION_PARTIAL;
        } else {
            $this->allocationStatus = self::ALLOCATION_PENDING;
        }
    }

    /**
     * 标记分配失败
     */
    public function markAllocationFailed(): void
    {
        $this->allocationStatus = self::ALLOCATION_FAILED;
    }

    /**
     * 计算并设置金额
     */
    public function calculateAmounts(): void
    {
        $this->totalPrice = bcmul($this->unitPrice, (string) $this->quantity, 2);
        $this->payableAmount = bcsub($this->totalPrice, $this->discountAmount, 2);
    }
}