<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 平台订单 - 从销售渠道同步的订单
 */
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
#[ORM\Index(name: 'idx_order_channel', columns: ['sales_channel_id'])]
#[ORM\Index(name: 'idx_order_status', columns: ['status'])]
#[ORM\Index(name: 'idx_order_external', columns: ['external_order_id'])]
#[ORM\Index(name: 'idx_order_placed', columns: ['placed_at'])]
#[ORM\HasLifecycleCallbacks]
class Order
{
    // 订单状态
    public const STATUS_PENDING = 'pending';           // 待处理（刚同步）
    public const STATUS_ALLOCATING = 'allocating';     // 分配中
    public const STATUS_ALLOCATED = 'allocated';       // 已分配（库存分配成功）
    public const STATUS_ALLOCATION_FAILED = 'allocation_failed';  // 分配失败（库存不足）
    public const STATUS_FULFILLING = 'fulfilling';     // 履约中
    public const STATUS_SHIPPED = 'shipped';           // 已发货
    public const STATUS_DELIVERED = 'delivered';       // 已签收
    public const STATUS_COMPLETED = 'completed';       // 已完成
    public const STATUS_CANCELLED = 'cancelled';       // 已取消

    // 支付状态
    public const PAYMENT_PENDING = 'pending';          // 待支付
    public const PAYMENT_PAID = 'paid';                // 已支付
    public const PAYMENT_REFUNDED = 'refunded';        // 已退款
    public const PAYMENT_PARTIAL_REFUNDED = 'partial_refunded';  // 部分退款

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(type: 'string', length: 30, unique: true)]
    private string $orderNo;  // 平台订单号

    #[ORM\ManyToOne(targetEntity: SalesChannel::class)]
    #[ORM\JoinColumn(name: 'sales_channel_id', nullable: false)]
    private SalesChannel $salesChannel;

    // 外部订单信息
    #[ORM\Column(type: 'string', length: 100)]
    private string $externalOrderId;  // 外部平台订单ID

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $externalOrderNo = null;  // 外部平台订单号（展示用）

    // 收货人信息
    #[ORM\Column(type: 'string', length: 50)]
    private string $receiverName;

    #[ORM\Column(type: 'string', length: 30)]
    private string $receiverPhone;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $receiverProvince = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $receiverCity = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $receiverDistrict = null;

    #[ORM\Column(type: 'string', length: 500)]
    private string $receiverAddress;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $receiverPostalCode = null;

    // 金额信息
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $totalAmount;  // 订单总金额

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $productAmount = '0.00';  // 商品金额

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $shippingAmount = '0.00';  // 运费

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $discountAmount = '0.00';  // 优惠金额

    #[ORM\Column(type: 'string', length: 3, options: ['default' => 'CNY'])]
    private string $currency = 'CNY';

    // 状态
    #[ORM\Column(type: 'string', length: 30)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'string', length: 30)]
    private string $paymentStatus = self::PAYMENT_PENDING;

    // 时间节点
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $placedAt;  // 下单时间（外部平台）

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;  // 支付时间

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $allocatedAt = null;  // 库存分配时间

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $shippedAt = null;  // 发货时间

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;  // 签收时间

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;  // 完成时间

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;  // 取消时间

    // 备注
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $buyerRemark = null;  // 买家留言

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $sellerRemark = null;  // 卖家备注

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $allocationFailReason = null;  // 分配失败原因

    // 同步信息
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $syncedAt;  // 同步时间

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $externalData = null;  // 原始订单数据（JSON）

    // 关联
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $items;

    #[ORM\OneToMany(targetEntity: Fulfillment::class, mappedBy: 'order', cascade: ['persist'])]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $fulfillments;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->orderNo = $this->generateOrderNo();
        $this->items = new ArrayCollection();
        $this->fulfillments = new ArrayCollection();
        $this->syncedAt = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function generateOrderNo(): string
    {
        // 格式：PO + 年月日 + 6位随机数，如 PO20241217123456
        return 'PO' . date('Ymd') . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrderNo(): string
    {
        return $this->orderNo;
    }

    public function setOrderNo(string $orderNo): static
    {
        $this->orderNo = $orderNo;
        return $this;
    }

    public function getSalesChannel(): SalesChannel
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(SalesChannel $salesChannel): static
    {
        $this->salesChannel = $salesChannel;
        return $this;
    }

    public function getExternalOrderId(): string
    {
        return $this->externalOrderId;
    }

    public function setExternalOrderId(string $externalOrderId): static
    {
        $this->externalOrderId = $externalOrderId;
        return $this;
    }

    public function getExternalOrderNo(): ?string
    {
        return $this->externalOrderNo;
    }

    public function setExternalOrderNo(?string $externalOrderNo): static
    {
        $this->externalOrderNo = $externalOrderNo;
        return $this;
    }

    public function getReceiverName(): string
    {
        return $this->receiverName;
    }

    public function setReceiverName(string $receiverName): static
    {
        $this->receiverName = $receiverName;
        return $this;
    }

    public function getReceiverPhone(): string
    {
        return $this->receiverPhone;
    }

    public function setReceiverPhone(string $receiverPhone): static
    {
        $this->receiverPhone = $receiverPhone;
        return $this;
    }

    public function getReceiverProvince(): ?string
    {
        return $this->receiverProvince;
    }

    public function setReceiverProvince(?string $receiverProvince): static
    {
        $this->receiverProvince = $receiverProvince;
        return $this;
    }

    public function getReceiverCity(): ?string
    {
        return $this->receiverCity;
    }

    public function setReceiverCity(?string $receiverCity): static
    {
        $this->receiverCity = $receiverCity;
        return $this;
    }

    public function getReceiverDistrict(): ?string
    {
        return $this->receiverDistrict;
    }

    public function setReceiverDistrict(?string $receiverDistrict): static
    {
        $this->receiverDistrict = $receiverDistrict;
        return $this;
    }

    public function getReceiverAddress(): string
    {
        return $this->receiverAddress;
    }

    public function setReceiverAddress(string $receiverAddress): static
    {
        $this->receiverAddress = $receiverAddress;
        return $this;
    }

    public function getReceiverPostalCode(): ?string
    {
        return $this->receiverPostalCode;
    }

    public function setReceiverPostalCode(?string $receiverPostalCode): static
    {
        $this->receiverPostalCode = $receiverPostalCode;
        return $this;
    }

    public function getReceiverFullAddress(): string
    {
        return ($this->receiverProvince ?? '') . ($this->receiverCity ?? '')
            . ($this->receiverDistrict ?? '') . $this->receiverAddress;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): static
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }

    public function getProductAmount(): string
    {
        return $this->productAmount;
    }

    public function setProductAmount(string $productAmount): static
    {
        $this->productAmount = $productAmount;
        return $this;
    }

    public function getShippingAmount(): string
    {
        return $this->shippingAmount;
    }

    public function setShippingAmount(string $shippingAmount): static
    {
        $this->shippingAmount = $shippingAmount;
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

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;
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

    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(string $paymentStatus): static
    {
        $this->paymentStatus = $paymentStatus;
        return $this;
    }

    public function getPlacedAt(): \DateTimeImmutable
    {
        return $this->placedAt;
    }

    public function setPlacedAt(\DateTimeImmutable $placedAt): static
    {
        $this->placedAt = $placedAt;
        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;
        return $this;
    }

    public function getAllocatedAt(): ?\DateTimeImmutable
    {
        return $this->allocatedAt;
    }

    public function setAllocatedAt(?\DateTimeImmutable $allocatedAt): static
    {
        $this->allocatedAt = $allocatedAt;
        return $this;
    }

    public function getShippedAt(): ?\DateTimeImmutable
    {
        return $this->shippedAt;
    }

    public function setShippedAt(?\DateTimeImmutable $shippedAt): static
    {
        $this->shippedAt = $shippedAt;
        return $this;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTimeImmutable $deliveredAt): static
    {
        $this->deliveredAt = $deliveredAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): static
    {
        $this->cancelledAt = $cancelledAt;
        return $this;
    }

    public function getBuyerRemark(): ?string
    {
        return $this->buyerRemark;
    }

    public function setBuyerRemark(?string $buyerRemark): static
    {
        $this->buyerRemark = $buyerRemark;
        return $this;
    }

    public function getSellerRemark(): ?string
    {
        return $this->sellerRemark;
    }

    public function setSellerRemark(?string $sellerRemark): static
    {
        $this->sellerRemark = $sellerRemark;
        return $this;
    }

    public function getAllocationFailReason(): ?string
    {
        return $this->allocationFailReason;
    }

    public function setAllocationFailReason(?string $allocationFailReason): static
    {
        $this->allocationFailReason = $allocationFailReason;
        return $this;
    }

    public function getSyncedAt(): \DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(\DateTimeImmutable $syncedAt): static
    {
        $this->syncedAt = $syncedAt;
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

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrder($this);
        }
        return $this;
    }

    public function removeItem(OrderItem $item): static
    {
        $this->items->removeElement($item);
        return $this;
    }

    /**
     * @return Collection<int, Fulfillment>
     */
    public function getFulfillments(): Collection
    {
        return $this->fulfillments;
    }

    public function addFulfillment(Fulfillment $fulfillment): static
    {
        if (!$this->fulfillments->contains($fulfillment)) {
            $this->fulfillments->add($fulfillment);
            $fulfillment->setOrder($this);
        }
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

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAllocated(): bool
    {
        return $this->status === self::STATUS_ALLOCATED;
    }

    public function isAllocationFailed(): bool
    {
        return $this->status === self::STATUS_ALLOCATION_FAILED;
    }

    public function isFulfilling(): bool
    {
        return $this->status === self::STATUS_FULFILLING;
    }

    public function isShipped(): bool
    {
        return $this->status === self::STATUS_SHIPPED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isPaid(): bool
    {
        return $this->paymentStatus === self::PAYMENT_PAID;
    }

    public function canAllocate(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->isPaid();
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_ALLOCATION_FAILED,
        ], true);
    }

    /**
     * 获取订单商品总数量
     */
    public function getTotalQuantity(): int
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->getQuantity();
        }
        return $total;
    }

    /**
     * 标记为分配中
     */
    public function markAllocating(): void
    {
        $this->status = self::STATUS_ALLOCATING;
    }

    /**
     * 标记分配成功
     */
    public function markAllocated(): void
    {
        $this->status = self::STATUS_ALLOCATED;
        $this->allocatedAt = new \DateTimeImmutable();
    }

    /**
     * 标记分配失败
     */
    public function markAllocationFailed(string $reason): void
    {
        $this->status = self::STATUS_ALLOCATION_FAILED;
        $this->allocationFailReason = $reason;
    }

    /**
     * 标记履约中
     */
    public function markFulfilling(): void
    {
        $this->status = self::STATUS_FULFILLING;
    }

    /**
     * 标记已发货
     */
    public function markShipped(): void
    {
        $this->status = self::STATUS_SHIPPED;
        $this->shippedAt = new \DateTimeImmutable();
    }

    /**
     * 标记已签收
     */
    public function markDelivered(): void
    {
        $this->status = self::STATUS_DELIVERED;
        $this->deliveredAt = new \DateTimeImmutable();
    }

    /**
     * 标记已完成
     */
    public function markCompleted(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = new \DateTimeImmutable();
    }

    /**
     * 标记已取消
     */
    public function markCancelled(): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->cancelledAt = new \DateTimeImmutable();
    }
}