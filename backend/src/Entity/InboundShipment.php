<?php

namespace App\Entity;

use App\Repository\InboundShipmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 送仓发货信息 - 商户发货的物流信息
 */
#[ORM\Entity(repositoryClass: InboundShipmentRepository::class)]
#[ORM\Table(name: 'inbound_shipments')]
#[ORM\Index(name: 'idx_inbound_shipment_order', columns: ['inbound_order_id'])]
#[ORM\Index(name: 'idx_inbound_shipment_tracking', columns: ['trackingNumber'])]
#[ORM\Index(name: 'idx_inbound_shipment_carrier', columns: ['carrierCode'])]
#[ORM\HasLifecycleCallbacks]
class InboundShipment
{
    // 物流状态
    public const STATUS_PENDING = 'pending';       // 待揽收
    public const STATUS_PICKED = 'picked';         // 已揽收
    public const STATUS_IN_TRANSIT = 'in_transit'; // 运输中
    public const STATUS_DELIVERED = 'delivered';   // 已送达
    public const STATUS_EXCEPTION = 'exception';   // 异常

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\OneToOne(targetEntity: InboundOrder::class, inversedBy: 'shipment')]
    #[ORM\JoinColumn(nullable: false)]
    private InboundOrder $inboundOrder;

    // 物流信息
    #[ORM\Column(length: 20)]
    private string $carrierCode;  // 物流公司代码，如：SF, JD, ZTO

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $carrierName = null;  // 物流公司名称

    #[ORM\Column(length: 50)]
    private string $trackingNumber;  // 运单号

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    // 发货人信息
    #[ORM\Column(length: 50)]
    private string $senderName;

    #[ORM\Column(length: 20)]
    private string $senderPhone;

    #[ORM\Column(length: 255)]
    private string $senderAddress;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $senderProvince = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $senderCity = null;

    // 包裹信息
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $boxCount = 1;  // 箱数/包裹数

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $totalWeight = null;  // 总重量（kg）

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $totalVolume = null;  // 总体积（m³）

    // 时间节点
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $shippedAt;  // 发货时间

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $estimatedArrivalDate = null;  // 预计到达日期

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;  // 实际送达时间

    // 物流轨迹（JSON 存储）
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $trackingHistory = null;  // 物流轨迹，如：[{"time": "2023-12-17 10:00", "status": "picked", "location": "上海", "desc": "已揽收"}]

    // 备注
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->shippedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
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

    public function getCarrierCode(): string
    {
        return $this->carrierCode;
    }

    public function setCarrierCode(string $carrierCode): static
    {
        $this->carrierCode = $carrierCode;
        return $this;
    }

    public function getCarrierName(): ?string
    {
        return $this->carrierName;
    }

    public function setCarrierName(?string $carrierName): static
    {
        $this->carrierName = $carrierName;
        return $this;
    }

    public function getTrackingNumber(): string
    {
        return $this->trackingNumber;
    }

    public function setTrackingNumber(string $trackingNumber): static
    {
        $this->trackingNumber = $trackingNumber;
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

    public function getSenderName(): string
    {
        return $this->senderName;
    }

    public function setSenderName(string $senderName): static
    {
        $this->senderName = $senderName;
        return $this;
    }

    public function getSenderPhone(): string
    {
        return $this->senderPhone;
    }

    public function setSenderPhone(string $senderPhone): static
    {
        $this->senderPhone = $senderPhone;
        return $this;
    }

    public function getSenderAddress(): string
    {
        return $this->senderAddress;
    }

    public function setSenderAddress(string $senderAddress): static
    {
        $this->senderAddress = $senderAddress;
        return $this;
    }

    public function getSenderProvince(): ?string
    {
        return $this->senderProvince;
    }

    public function setSenderProvince(?string $senderProvince): static
    {
        $this->senderProvince = $senderProvince;
        return $this;
    }

    public function getSenderCity(): ?string
    {
        return $this->senderCity;
    }

    public function setSenderCity(?string $senderCity): static
    {
        $this->senderCity = $senderCity;
        return $this;
    }

    public function getBoxCount(): int
    {
        return $this->boxCount;
    }

    public function setBoxCount(int $boxCount): static
    {
        $this->boxCount = $boxCount;
        return $this;
    }

    public function getTotalWeight(): ?string
    {
        return $this->totalWeight;
    }

    public function setTotalWeight(?string $totalWeight): static
    {
        $this->totalWeight = $totalWeight;
        return $this;
    }

    public function getTotalVolume(): ?string
    {
        return $this->totalVolume;
    }

    public function setTotalVolume(?string $totalVolume): static
    {
        $this->totalVolume = $totalVolume;
        return $this;
    }

    public function getShippedAt(): \DateTimeImmutable
    {
        return $this->shippedAt;
    }

    public function setShippedAt(\DateTimeImmutable $shippedAt): static
    {
        $this->shippedAt = $shippedAt;
        return $this;
    }

    public function getEstimatedArrivalDate(): ?\DateTimeImmutable
    {
        return $this->estimatedArrivalDate;
    }

    public function setEstimatedArrivalDate(?\DateTimeImmutable $estimatedArrivalDate): static
    {
        $this->estimatedArrivalDate = $estimatedArrivalDate;
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

    public function getTrackingHistory(): ?array
    {
        return $this->trackingHistory;
    }

    public function setTrackingHistory(?array $trackingHistory): static
    {
        $this->trackingHistory = $trackingHistory;
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
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    // 便捷方法

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function isInTransit(): bool
    {
        return $this->status === self::STATUS_IN_TRANSIT;
    }

    /**
     * 添加物流轨迹
     */
    public function addTrackingEvent(string $status, string $description, ?string $location = null): void
    {
        $event = [
            'time' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('c'),
            'status' => $status,
            'desc' => $description,
        ];

        if ($location !== null) {
            $event['location'] = $location;
        }

        $history = $this->trackingHistory ?? [];
        array_unshift($history, $event);  // 最新的在前面
        $this->trackingHistory = $history;
    }

    /**
     * 获取最新物流状态
     */
    public function getLatestTrackingEvent(): ?array
    {
        return $this->trackingHistory[0] ?? null;
    }

    /**
     * 获取发货人完整地址
     */
    public function getSenderFullAddress(): string
    {
        return ($this->senderProvince ?? '') . ($this->senderCity ?? '') . $this->senderAddress;
    }
}
