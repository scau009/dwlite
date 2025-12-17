<?php

namespace App\Entity;

use App\Repository\OutboundOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 出库单 - 平台仓库发货时创建，用于与 WMS 系统对接
 */
#[ORM\Entity(repositoryClass: OutboundOrderRepository::class)]
#[ORM\Table(name: 'outbound_orders')]
#[ORM\Index(name: 'idx_outbound_fulfillment', columns: ['fulfillment_id'])]
#[ORM\Index(name: 'idx_outbound_warehouse', columns: ['warehouse_id'])]
#[ORM\Index(name: 'idx_outbound_status', columns: ['status'])]
#[ORM\Index(name: 'idx_outbound_sync_status', columns: ['sync_status'])]
#[ORM\Index(name: 'idx_outbound_external', columns: ['external_id'])]
#[ORM\HasLifecycleCallbacks]
class OutboundOrder
{
    // 出库单状态
    public const STATUS_PENDING = 'pending';         // 待处理
    public const STATUS_PICKING = 'picking';         // 拣货中
    public const STATUS_PACKING = 'packing';         // 打包中
    public const STATUS_READY = 'ready';             // 待发货（打包完成）
    public const STATUS_SHIPPED = 'shipped';         // 已发货
    public const STATUS_CANCELLED = 'cancelled';     // 已取消

    // 同步状态
    public const SYNC_PENDING = 'pending';           // 待同步
    public const SYNC_SYNCED = 'synced';             // 已同步
    public const SYNC_FAILED = 'failed';             // 同步失败
    public const SYNC_CALLBACK = 'callback';         // 已回调（WMS 已确认）

    // 出库类型
    public const TYPE_SALES = 'sales';               // 销售出库
    public const TYPE_RETURN_TO_MERCHANT = 'return_to_merchant';  // 退还商家
    public const TYPE_TRANSFER = 'transfer';         // 调拨出库
    public const TYPE_SCRAP = 'scrap';               // 报废出库

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(type: 'string', length: 30, unique: true)]
    private string $outboundNo;  // 出库单号

    #[ORM\OneToOne(targetEntity: Fulfillment::class, inversedBy: 'outboundOrder')]
    #[ORM\JoinColumn(name: 'fulfillment_id', nullable: false)]
    private Fulfillment $fulfillment;

    #[ORM\ManyToOne(targetEntity: Warehouse::class)]
    #[ORM\JoinColumn(name: 'warehouse_id', nullable: false)]
    private Warehouse $warehouse;

    // 出库类型
    #[ORM\Column(type: 'string', length: 30)]
    private string $outboundType = self::TYPE_SALES;

    // 状态
    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_PENDING;

    // WMS 同步相关
    #[ORM\Column(type: 'string', length: 20)]
    private string $syncStatus = self::SYNC_PENDING;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $externalId = null;  // WMS 系统返回的出库单ID

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $syncError = null;  // 同步错误信息

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $syncAttempts = 0;  // 同步尝试次数

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $syncedAt = null;  // 成功同步时间

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSyncAt = null;  // 最后同步尝试时间

    // 收货人信息（从订单快照）
    #[ORM\Column(type: 'string', length: 50)]
    private string $receiverName;

    #[ORM\Column(type: 'string', length: 30)]
    private string $receiverPhone;

    #[ORM\Column(type: 'string', length: 500)]
    private string $receiverAddress;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $receiverPostalCode = null;

    // 物流信息
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $shippingCarrier = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $trackingNumber = null;

    // 时间节点
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $pickingStartedAt = null;  // 开始拣货时间

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $pickingCompletedAt = null;  // 拣货完成时间

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $packingStartedAt = null;  // 开始打包时间

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $packingCompletedAt = null;  // 打包完成时间

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $shippedAt = null;  // 发货时间

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;  // 取消时间

    // 取消原因
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $cancelReason = null;

    // 备注
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $remark = null;

    // 关联明细
    #[ORM\OneToMany(targetEntity: OutboundOrderItem::class, mappedBy: 'outboundOrder', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->outboundNo = $this->generateOutboundNo();
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function generateOutboundNo(): string
    {
        // 格式：OB + 年月日 + 6位随机数
        return 'OB' . date('Ymd') . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOutboundNo(): string
    {
        return $this->outboundNo;
    }

    public function setOutboundNo(string $outboundNo): static
    {
        $this->outboundNo = $outboundNo;
        return $this;
    }

    public function getFulfillment(): Fulfillment
    {
        return $this->fulfillment;
    }

    public function setFulfillment(Fulfillment $fulfillment): static
    {
        $this->fulfillment = $fulfillment;
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

    public function getOutboundType(): string
    {
        return $this->outboundType;
    }

    public function setOutboundType(string $outboundType): static
    {
        $this->outboundType = $outboundType;
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

    public function getSyncStatus(): string
    {
        return $this->syncStatus;
    }

    public function setSyncStatus(string $syncStatus): static
    {
        $this->syncStatus = $syncStatus;
        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): static
    {
        $this->externalId = $externalId;
        return $this;
    }

    public function getSyncError(): ?string
    {
        return $this->syncError;
    }

    public function setSyncError(?string $syncError): static
    {
        $this->syncError = $syncError;
        return $this;
    }

    public function getSyncAttempts(): int
    {
        return $this->syncAttempts;
    }

    public function setSyncAttempts(int $syncAttempts): static
    {
        $this->syncAttempts = $syncAttempts;
        return $this;
    }

    public function getSyncedAt(): ?\DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(?\DateTimeImmutable $syncedAt): static
    {
        $this->syncedAt = $syncedAt;
        return $this;
    }

    public function getLastSyncAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncAt;
    }

    public function setLastSyncAt(?\DateTimeImmutable $lastSyncAt): static
    {
        $this->lastSyncAt = $lastSyncAt;
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

    public function getPickingStartedAt(): ?\DateTimeImmutable
    {
        return $this->pickingStartedAt;
    }

    public function setPickingStartedAt(?\DateTimeImmutable $pickingStartedAt): static
    {
        $this->pickingStartedAt = $pickingStartedAt;
        return $this;
    }

    public function getPickingCompletedAt(): ?\DateTimeImmutable
    {
        return $this->pickingCompletedAt;
    }

    public function setPickingCompletedAt(?\DateTimeImmutable $pickingCompletedAt): static
    {
        $this->pickingCompletedAt = $pickingCompletedAt;
        return $this;
    }

    public function getPackingStartedAt(): ?\DateTimeImmutable
    {
        return $this->packingStartedAt;
    }

    public function setPackingStartedAt(?\DateTimeImmutable $packingStartedAt): static
    {
        $this->packingStartedAt = $packingStartedAt;
        return $this;
    }

    public function getPackingCompletedAt(): ?\DateTimeImmutable
    {
        return $this->packingCompletedAt;
    }

    public function setPackingCompletedAt(?\DateTimeImmutable $packingCompletedAt): static
    {
        $this->packingCompletedAt = $packingCompletedAt;
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
     * @return Collection<int, OutboundOrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OutboundOrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOutboundOrder($this);
        }
        return $this;
    }

    public function removeItem(OutboundOrderItem $item): static
    {
        $this->items->removeElement($item);
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

    public function isPicking(): bool
    {
        return $this->status === self::STATUS_PICKING;
    }

    public function isPacking(): bool
    {
        return $this->status === self::STATUS_PACKING;
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function isShipped(): bool
    {
        return $this->status === self::STATUS_SHIPPED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isSynced(): bool
    {
        return $this->syncStatus === self::SYNC_SYNCED || $this->syncStatus === self::SYNC_CALLBACK;
    }

    public function canSync(): bool
    {
        return $this->syncStatus === self::SYNC_PENDING || $this->syncStatus === self::SYNC_FAILED;
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_PICKING,
        ], true);
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
     * 记录同步成功
     */
    public function markSynced(string $externalId): void
    {
        $this->syncStatus = self::SYNC_SYNCED;
        $this->externalId = $externalId;
        $this->syncedAt = new \DateTimeImmutable();
        $this->lastSyncAt = new \DateTimeImmutable();
        $this->syncError = null;
    }

    /**
     * 记录同步失败
     */
    public function markSyncFailed(string $error): void
    {
        $this->syncStatus = self::SYNC_FAILED;
        $this->syncError = $error;
        $this->lastSyncAt = new \DateTimeImmutable();
        $this->syncAttempts++;
    }

    /**
     * 开始拣货
     */
    public function startPicking(): void
    {
        $this->status = self::STATUS_PICKING;
        $this->pickingStartedAt = new \DateTimeImmutable();
    }

    /**
     * 完成拣货
     */
    public function completePicking(): void
    {
        $this->pickingCompletedAt = new \DateTimeImmutable();
    }

    /**
     * 开始打包
     */
    public function startPacking(): void
    {
        $this->status = self::STATUS_PACKING;
        $this->packingStartedAt = new \DateTimeImmutable();
    }

    /**
     * 完成打包
     */
    public function completePacking(): void
    {
        $this->status = self::STATUS_READY;
        $this->packingCompletedAt = new \DateTimeImmutable();
    }

    /**
     * 标记已发货
     */
    public function markShipped(string $carrier, string $trackingNumber): void
    {
        $this->status = self::STATUS_SHIPPED;
        $this->shippingCarrier = $carrier;
        $this->trackingNumber = $trackingNumber;
        $this->shippedAt = new \DateTimeImmutable();

        // 同步到履约单
        $this->fulfillment->markShipped($carrier, $trackingNumber);
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
     * 从订单快照收货人信息
     */
    public function snapshotReceiverFromOrder(Order $order): void
    {
        $this->receiverName = $order->getReceiverName();
        $this->receiverPhone = $order->getReceiverPhone();
        $this->receiverAddress = $order->getReceiverFullAddress();
        $this->receiverPostalCode = $order->getReceiverPostalCode();
    }

    /**
     * 从履约单创建出库单的工厂方法
     */
    public static function createFromFulfillment(Fulfillment $fulfillment): static
    {
        $outbound = new static();
        $outbound->setFulfillment($fulfillment);
        $outbound->setWarehouse($fulfillment->getWarehouse());
        $outbound->snapshotReceiverFromOrder($fulfillment->getOrder());
        return $outbound;
    }
}