<?php

namespace App\Entity;

use App\Repository\FulfillmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 履约单 - 订单库存分配成功后创建
 *
 * 一个订单可能有多个履约单（不同仓库/不同商家）
 */
#[ORM\Entity(repositoryClass: FulfillmentRepository::class)]
#[ORM\Table(name: 'fulfillments')]
#[ORM\Index(name: 'idx_fulfillment_order', columns: ['order_id'])]
#[ORM\Index(name: 'idx_fulfillment_type', columns: ['fulfillment_type'])]
#[ORM\Index(name: 'idx_fulfillment_status', columns: ['status'])]
#[ORM\Index(name: 'idx_fulfillment_merchant', columns: ['merchant_id'])]
#[ORM\Index(name: 'idx_fulfillment_warehouse', columns: ['warehouse_id'])]
#[ORM\HasLifecycleCallbacks]
class Fulfillment
{
    // 履约类型
    public const TYPE_PLATFORM_WAREHOUSE = 'platform_warehouse';   // 平台仓发货
    public const TYPE_MERCHANT_WAREHOUSE = 'merchant_warehouse';   // 商家自有仓发货

    // 履约状态
    public const STATUS_PENDING = 'pending';           // 待处理
    public const STATUS_PROCESSING = 'processing';    // 处理中（平台仓：出库作业中；商家仓：已通知商家）
    public const STATUS_SHIPPED = 'shipped';          // 已发货
    public const STATUS_DELIVERED = 'delivered';      // 已签收
    public const STATUS_CANCELLED = 'cancelled';      // 已取消

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(type: 'string', length: 30, unique: true)]
    private string $fulfillmentNo;  // 履约单号

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'fulfillments')]
    #[ORM\JoinColumn(name: 'order_id', nullable: false)]
    private Order $order;

    // 履约类型
    #[ORM\Column(type: 'string', length: 30)]
    private string $fulfillmentType;

    // 商家（商家自发货时关联）
    #[ORM\ManyToOne(targetEntity: Merchant::class)]
    #[ORM\JoinColumn(name: 'merchant_id', nullable: true)]
    private ?Merchant $merchant = null;

    // 仓库
    #[ORM\ManyToOne(targetEntity: Warehouse::class)]
    #[ORM\JoinColumn(name: 'warehouse_id', nullable: false)]
    private Warehouse $warehouse;

    // 状态
    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_PENDING;

    // 物流信息
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $shippingCarrier = null;  // 物流公司

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $trackingNumber = null;  // 物流单号

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $trackingUrl = null;  // 物流追踪链接

    // 时间节点
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $notifiedAt = null;  // 通知时间（商家仓：通知商家时间）

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $shippedAt = null;  // 发货时间

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;  // 签收时间

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;  // 取消时间

    // 取消原因
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $cancelReason = null;

    // 备注
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $remark = null;

    // 关联
    #[ORM\OneToMany(targetEntity: FulfillmentItem::class, mappedBy: 'fulfillment', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    // 出库单（平台仓发货时关联）
    #[ORM\OneToOne(targetEntity: OutboundOrder::class, mappedBy: 'fulfillment', cascade: ['persist'])]
    private ?OutboundOrder $outboundOrder = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->fulfillmentNo = $this->generateFulfillmentNo();
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function generateFulfillmentNo(): string
    {
        // 格式：FF + 年月日 + 6位随机数
        return 'FF' . date('Ymd') . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getFulfillmentNo(): string
    {
        return $this->fulfillmentNo;
    }

    public function setFulfillmentNo(string $fulfillmentNo): static
    {
        $this->fulfillmentNo = $fulfillmentNo;
        return $this;
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

    public function getFulfillmentType(): string
    {
        return $this->fulfillmentType;
    }

    public function setFulfillmentType(string $fulfillmentType): static
    {
        $this->fulfillmentType = $fulfillmentType;
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

    public function getWarehouse(): Warehouse
    {
        return $this->warehouse;
    }

    public function setWarehouse(Warehouse $warehouse): static
    {
        $this->warehouse = $warehouse;
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

    public function getShippingCarrier(): ?string
    {
        return $this->shippingCarrier;
    }

    public function setShippingCarrier(?string $shippingCarrier): static
    {
        $this->shippingCarrier = $shippingCarrier;
        return $this;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function setTrackingNumber(?string $trackingNumber): static
    {
        $this->trackingNumber = $trackingNumber;
        return $this;
    }

    public function getTrackingUrl(): ?string
    {
        return $this->trackingUrl;
    }

    public function setTrackingUrl(?string $trackingUrl): static
    {
        $this->trackingUrl = $trackingUrl;
        return $this;
    }

    public function getNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->notifiedAt;
    }

    public function setNotifiedAt(?\DateTimeImmutable $notifiedAt): static
    {
        $this->notifiedAt = $notifiedAt;
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

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): static
    {
        $this->cancelledAt = $cancelledAt;
        return $this;
    }

    public function getCancelReason(): ?string
    {
        return $this->cancelReason;
    }

    public function setCancelReason(?string $cancelReason): static
    {
        $this->cancelReason = $cancelReason;
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

    /**
     * @return Collection<int, FulfillmentItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(FulfillmentItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setFulfillment($this);
        }
        return $this;
    }

    public function removeItem(FulfillmentItem $item): static
    {
        $this->items->removeElement($item);
        return $this;
    }

    public function getOutboundOrder(): ?OutboundOrder
    {
        return $this->outboundOrder;
    }

    public function setOutboundOrder(?OutboundOrder $outboundOrder): static
    {
        $this->outboundOrder = $outboundOrder;
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

    public function isPlatformWarehouse(): bool
    {
        return $this->fulfillmentType === self::TYPE_PLATFORM_WAREHOUSE;
    }

    public function isMerchantWarehouse(): bool
    {
        return $this->fulfillmentType === self::TYPE_MERCHANT_WAREHOUSE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isShipped(): bool
    {
        return $this->status === self::STATUS_SHIPPED;
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
        ], true);
    }

    /**
     * 是否需要出库单
     */
    public function needsOutboundOrder(): bool
    {
        return $this->isPlatformWarehouse();
    }

    /**
     * 获取总数量
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
     * 标记为处理中
     */
    public function markProcessing(): void
    {
        $this->status = self::STATUS_PROCESSING;
        if ($this->isMerchantWarehouse()) {
            $this->notifiedAt = new \DateTimeImmutable();
        }
    }

    /**
     * 标记已发货
     */
    public function markShipped(string $carrier, string $trackingNumber, ?string $trackingUrl = null): void
    {
        $this->status = self::STATUS_SHIPPED;
        $this->shippingCarrier = $carrier;
        $this->trackingNumber = $trackingNumber;
        $this->trackingUrl = $trackingUrl;
        $this->shippedAt = new \DateTimeImmutable();

        // 更新订单明细的发货数量
        foreach ($this->items as $item) {
            $item->getOrderItem()->addShippedQuantity($item->getQuantity());
        }
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
     * 标记已取消
     */
    public function markCancelled(string $reason): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->cancelReason = $reason;
        $this->cancelledAt = new \DateTimeImmutable();
    }

    /**
     * 创建平台仓履约单的工厂方法
     */
    public static function createForPlatformWarehouse(Order $order, Warehouse $warehouse): static
    {
        $fulfillment = new static();
        $fulfillment->setOrder($order);
        $fulfillment->setWarehouse($warehouse);
        $fulfillment->setFulfillmentType(self::TYPE_PLATFORM_WAREHOUSE);
        return $fulfillment;
    }

    /**
     * 创建商家仓履约单的工厂方法
     */
    public static function createForMerchantWarehouse(Order $order, Warehouse $warehouse, Merchant $merchant): static
    {
        $fulfillment = new static();
        $fulfillment->setOrder($order);
        $fulfillment->setWarehouse($warehouse);
        $fulfillment->setMerchant($merchant);
        $fulfillment->setFulfillmentType(self::TYPE_MERCHANT_WAREHOUSE);
        return $fulfillment;
    }
}