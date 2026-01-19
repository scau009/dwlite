<?php

namespace App\Entity;

use App\Repository\InventoryReservationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 库存预留 - 两层预留机制.
 *
 * 第一层：ChannelProduct 层预留（订单确认时）
 * 第二层：MerchantInventory 层锁定（履约分配时）
 */
#[ORM\Entity(repositoryClass: InventoryReservationRepository::class)]
#[ORM\Table(name: 'inventory_reservations')]
#[ORM\Index(name: 'idx_reservation_channel_product', columns: ['channel_product_id', 'status'])]
#[ORM\Index(name: 'idx_reservation_inventory', columns: ['inventory_id', 'status'])]
#[ORM\Index(name: 'idx_reservation_order', columns: ['order_id'])]
#[ORM\Index(name: 'idx_reservation_order_item', columns: ['order_item_id'])]
#[ORM\Index(name: 'idx_reservation_fulfillment', columns: ['fulfillment_id'])]
#[ORM\Index(name: 'idx_reservation_expires', columns: ['expires_at', 'status'])]
#[ORM\Index(name: 'idx_reservation_status', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class InventoryReservation
{
    // 预留状态
    public const STATUS_RESERVED = 'reserved';      // 已预留（ChannelProduct层）
    public const STATUS_ALLOCATED = 'allocated';    // 已分配（关联了MerchantInventory）
    public const STATUS_LOCKED = 'locked';          // 已锁定（出库单提交）
    public const STATUS_RELEASED = 'released';      // 已释放（订单取消）
    public const STATUS_EXPIRED = 'expired';        // 已过期
    public const STATUS_COMPLETED = 'completed';    // 已完成（发货后）

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    // 第一层：ChannelProduct 级预留
    #[ORM\ManyToOne(targetEntity: ChannelProduct::class)]
    #[ORM\JoinColumn(name: 'channel_product_id', nullable: false)]
    private ChannelProduct $channelProduct;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(name: 'order_id', nullable: false)]
    private Order $order;

    #[ORM\ManyToOne(targetEntity: OrderItem::class)]
    #[ORM\JoinColumn(name: 'order_item_id', nullable: false)]
    private OrderItem $orderItem;

    #[ORM\Column(type: 'integer')]
    private int $quantity;  // 预留数量

    // 第二层：MerchantInventory 级锁定（分配后填充）
    #[ORM\ManyToOne(targetEntity: MerchantInventory::class)]
    #[ORM\JoinColumn(name: 'inventory_id', nullable: true)]
    private ?MerchantInventory $inventory = null;

    #[ORM\ManyToOne(targetEntity: Fulfillment::class)]
    #[ORM\JoinColumn(name: 'fulfillment_id', nullable: true)]
    private ?Fulfillment $fulfillment = null;

    #[ORM\ManyToOne(targetEntity: FulfillmentItem::class)]
    #[ORM\JoinColumn(name: 'fulfillment_item_id', nullable: true)]
    private ?FulfillmentItem $fulfillmentItem = null;

    // 状态
    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_RESERVED;

    // 时间节点
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;  // 预留过期时间 (UTC)

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $allocatedAt = null;  // 分配时间

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lockedAt = null;  // 锁定时间

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $releasedAt = null;  // 释放时间

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;  // 完成时间

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

    public function getChannelProduct(): ChannelProduct
    {
        return $this->channelProduct;
    }

    public function setChannelProduct(ChannelProduct $channelProduct): static
    {
        $this->channelProduct = $channelProduct;

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

    public function getOrderItem(): OrderItem
    {
        return $this->orderItem;
    }

    public function setOrderItem(OrderItem $orderItem): static
    {
        $this->orderItem = $orderItem;

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

    public function getInventory(): ?MerchantInventory
    {
        return $this->inventory;
    }

    public function setInventory(?MerchantInventory $inventory): static
    {
        $this->inventory = $inventory;

        return $this;
    }

    public function getFulfillment(): ?Fulfillment
    {
        return $this->fulfillment;
    }

    public function setFulfillment(?Fulfillment $fulfillment): static
    {
        $this->fulfillment = $fulfillment;

        return $this;
    }

    public function getFulfillmentItem(): ?FulfillmentItem
    {
        return $this->fulfillmentItem;
    }

    public function setFulfillmentItem(?FulfillmentItem $fulfillmentItem): static
    {
        $this->fulfillmentItem = $fulfillmentItem;

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

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

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

    public function getLockedAt(): ?\DateTimeImmutable
    {
        return $this->lockedAt;
    }

    public function setLockedAt(?\DateTimeImmutable $lockedAt): static
    {
        $this->lockedAt = $lockedAt;

        return $this;
    }

    public function getReleasedAt(): ?\DateTimeImmutable
    {
        return $this->releasedAt;
    }

    public function setReleasedAt(?\DateTimeImmutable $releasedAt): static
    {
        $this->releasedAt = $releasedAt;

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

    // 便捷方法

    public function isReserved(): bool
    {
        return $this->status === self::STATUS_RESERVED;
    }

    public function isAllocated(): bool
    {
        return $this->status === self::STATUS_ALLOCATED;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    public function isReleased(): bool
    {
        return $this->status === self::STATUS_RELEASED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * 检查是否活跃（非终结状态）.
     */
    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_RESERVED,
            self::STATUS_ALLOCATED,
            self::STATUS_LOCKED,
        ], true);
    }

    /**
     * 检查是否已过期.
     */
    public function isOverdue(): bool
    {
        if ($this->status !== self::STATUS_RESERVED && $this->status !== self::STATUS_ALLOCATED) {
            return false;
        }

        return $this->expiresAt < new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * 标记为已分配.
     */
    public function markAllocated(
        MerchantInventory $inventory,
        Fulfillment $fulfillment,
        FulfillmentItem $fulfillmentItem
    ): void {
        $this->inventory = $inventory;
        $this->fulfillment = $fulfillment;
        $this->fulfillmentItem = $fulfillmentItem;
        $this->status = self::STATUS_ALLOCATED;
        $this->allocatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * 标记为已锁定.
     */
    public function markLocked(): void
    {
        $this->status = self::STATUS_LOCKED;
        $this->lockedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * 标记为已释放.
     */
    public function markReleased(): void
    {
        $this->status = self::STATUS_RELEASED;
        $this->releasedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * 标记为已过期.
     */
    public function markExpired(): void
    {
        $this->status = self::STATUS_EXPIRED;
        $this->releasedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * 标记为已完成.
     */
    public function markCompleted(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * 创建渠道商品预留.
     */
    public static function createForChannelProduct(
        ChannelProduct $channelProduct,
        Order $order,
        OrderItem $orderItem,
        int $quantity,
        int $ttlMinutes = 60
    ): self {
        $reservation = new self();
        $reservation->channelProduct = $channelProduct;
        $reservation->order = $order;
        $reservation->orderItem = $orderItem;
        $reservation->quantity = $quantity;
        $reservation->expiresAt = new \DateTimeImmutable(
            sprintf('+%d minutes', $ttlMinutes),
            new \DateTimeZone('UTC')
        );

        return $reservation;
    }
}
