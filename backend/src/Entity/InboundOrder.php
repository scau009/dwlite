<?php

namespace App\Entity;

use App\Repository\InboundOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 送仓单/入库单 - 商户向仓库送货的主单据
 */
#[ORM\Entity(repositoryClass: InboundOrderRepository::class)]
#[ORM\Table(name: 'inbound_orders')]
#[ORM\Index(name: 'idx_inbound_order_no', columns: ['orderNo'])]
#[ORM\Index(name: 'idx_inbound_merchant', columns: ['merchant_id'])]
#[ORM\Index(name: 'idx_inbound_warehouse', columns: ['warehouse_id'])]
#[ORM\Index(name: 'idx_inbound_status', columns: ['status'])]
#[ORM\Index(name: 'idx_inbound_created', columns: ['createdAt'])]
#[ORM\HasLifecycleCallbacks]
class InboundOrder
{
    // 送仓单状态
    public const STATUS_DRAFT = 'draft';                   // 草稿
    public const STATUS_PENDING = 'pending';               // 待发货（已提交）
    public const STATUS_SHIPPED = 'shipped';               // 已发货/在途
    public const STATUS_ARRIVED = 'arrived';               // 已到达仓库
    public const STATUS_RECEIVING = 'receiving';           // 收货中/清点中
    public const STATUS_COMPLETED = 'completed';           // 已完成（全部入库）
    public const STATUS_PARTIAL_COMPLETED = 'partial_completed'; // 部分完成（有差异）
    public const STATUS_CANCELLED = 'cancelled';           // 已取消

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(length: 32, unique: true)]
    private string $orderNo;  // 送仓单号，如：IB20231217000001

    #[ORM\ManyToOne(targetEntity: Merchant::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Merchant $merchant;

    #[ORM\ManyToOne(targetEntity: Warehouse::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Warehouse $warehouse;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_DRAFT;

    // 数量统计
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $totalSkuCount = 0;  // SKU 种类数

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $totalQuantity = 0;  // 预报总数量

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $receivedQuantity = 0;  // 实收总数量

    // 时间节点
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $expectedArrivalDate = null;  // 预计到货日期

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;  // 提交时间（从草稿变为待发货）

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $shippedAt = null;  // 发货时间

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $arrivedAt = null;  // 到达仓库时间

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;  // 完成时间

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;  // 取消时间

    // 备注
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $merchantNotes = null;  // 商户备注

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $warehouseNotes = null;  // 仓库备注

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $cancelReason = null;  // 取消原因

    // 关联
    #[ORM\OneToMany(targetEntity: InboundOrderItem::class, mappedBy: 'inboundOrder', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\OneToOne(targetEntity: InboundShipment::class, mappedBy: 'inboundOrder', cascade: ['persist', 'remove'])]
    private ?InboundShipment $shipment = null;

    #[ORM\OneToMany(targetEntity: InboundException::class, mappedBy: 'inboundOrder')]
    private Collection $exceptions;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->items = new ArrayCollection();
        $this->exceptions = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
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

    public function getMerchant(): Merchant
    {
        return $this->merchant;
    }

    public function setMerchant(Merchant $merchant): static
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

    public function getTotalSkuCount(): int
    {
        return $this->totalSkuCount;
    }

    public function setTotalSkuCount(int $totalSkuCount): static
    {
        $this->totalSkuCount = $totalSkuCount;
        return $this;
    }

    public function getTotalQuantity(): int
    {
        return $this->totalQuantity;
    }

    public function setTotalQuantity(int $totalQuantity): static
    {
        $this->totalQuantity = $totalQuantity;
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

    public function getExpectedArrivalDate(): ?\DateTimeImmutable
    {
        return $this->expectedArrivalDate;
    }

    public function setExpectedArrivalDate(?\DateTimeImmutable $expectedArrivalDate): static
    {
        $this->expectedArrivalDate = $expectedArrivalDate;
        return $this;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(?\DateTimeImmutable $submittedAt): static
    {
        $this->submittedAt = $submittedAt;
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

    public function getArrivedAt(): ?\DateTimeImmutable
    {
        return $this->arrivedAt;
    }

    public function setArrivedAt(?\DateTimeImmutable $arrivedAt): static
    {
        $this->arrivedAt = $arrivedAt;
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

    public function getMerchantNotes(): ?string
    {
        return $this->merchantNotes;
    }

    public function setMerchantNotes(?string $merchantNotes): static
    {
        $this->merchantNotes = $merchantNotes;
        return $this;
    }

    public function getWarehouseNotes(): ?string
    {
        return $this->warehouseNotes;
    }

    public function setWarehouseNotes(?string $warehouseNotes): static
    {
        $this->warehouseNotes = $warehouseNotes;
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

    /**
     * @return Collection<int, InboundOrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(InboundOrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setInboundOrder($this);
        }
        return $this;
    }

    public function removeItem(InboundOrderItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getInboundOrder() === $this) {
                $item->setInboundOrder($this);
            }
        }
        return $this;
    }

    public function getShipment(): ?InboundShipment
    {
        return $this->shipment;
    }

    public function setShipment(?InboundShipment $shipment): static
    {
        $this->shipment = $shipment;
        return $this;
    }

    /**
     * @return Collection<int, InboundException>
     */
    public function getExceptions(): Collection
    {
        return $this->exceptions;
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

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
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

    public function hasException(): bool
    {
        return !$this->exceptions->isEmpty();
    }

    /**
     * 计算差异数量（预报 - 实收）
     */
    public function getQuantityDifference(): int
    {
        return $this->totalQuantity - $this->receivedQuantity;
    }

    /**
     * 是否有数量差异
     */
    public function hasQuantityDifference(): bool
    {
        return $this->getQuantityDifference() !== 0;
    }

    /**
     * 重新计算数量统计
     */
    public function recalculateTotals(): void
    {
        $this->totalSkuCount = $this->items->count();
        $this->totalQuantity = 0;
        $this->receivedQuantity = 0;

        foreach ($this->items as $item) {
            $this->totalQuantity += $item->getExpectedQuantity();
            $this->receivedQuantity += $item->getReceivedQuantity();
        }
    }

    /**
     * 提交送仓单（草稿 -> 待发货）
     */
    public function submit(): void
    {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new \LogicException('Only draft orders can be submitted');
        }
        $this->status = self::STATUS_PENDING;
        $this->submittedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * 标记为已发货
     */
    public function markAsShipped(): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \LogicException('Only pending orders can be shipped');
        }
        $this->status = self::STATUS_SHIPPED;
        $this->shippedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * 取消送仓单
     */
    public function cancel(string $reason): void
    {
        if (in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_PARTIAL_COMPLETED, self::STATUS_CANCELLED], true)) {
            throw new \LogicException('Cannot cancel completed or already cancelled orders');
        }
        $this->status = self::STATUS_CANCELLED;
        $this->cancelReason = $reason;
        $this->cancelledAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * 生成送仓单号
     */
    public static function generateOrderNo(): string
    {
        return 'IB' . date('Ymd') . strtoupper(substr((string) new Ulid(), -8));
    }
}