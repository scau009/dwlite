<?php

namespace App\Entity;

use App\Repository\InboundExceptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 入库异常单 - 入库时发现的数量差异或质量问题
 */
#[ORM\Entity(repositoryClass: InboundExceptionRepository::class)]
#[ORM\Table(name: 'inbound_exceptions')]
#[ORM\Index(name: 'idx_inbound_exc_no', columns: ['exceptionNo'])]
#[ORM\Index(name: 'idx_inbound_exc_order', columns: ['inbound_order_id'])]
#[ORM\Index(name: 'idx_inbound_exc_merchant', columns: ['merchant_id'])]
#[ORM\Index(name: 'idx_inbound_exc_status', columns: ['status'])]
#[ORM\Index(name: 'idx_inbound_exc_created', columns: ['createdAt'])]
#[ORM\HasLifecycleCallbacks]
class InboundException
{
    // 异常类型
    public const TYPE_QUANTITY_SHORT = 'quantity_short';     // 数量短少
    public const TYPE_QUANTITY_OVER = 'quantity_over';       // 数量超出
    public const TYPE_DAMAGED = 'damaged';                   // 货物损坏
    public const TYPE_WRONG_ITEM = 'wrong_item';             // 货物错误
    public const TYPE_QUALITY_ISSUE = 'quality_issue';       // 质量问题
    public const TYPE_PACKAGING = 'packaging';               // 包装问题
    public const TYPE_EXPIRED = 'expired';                   // 过期/临期
    public const TYPE_OTHER = 'other';                       // 其他

    // 状态
    public const STATUS_PENDING = 'pending';           // 待处理
    public const STATUS_PROCESSING = 'processing';     // 处理中
    public const STATUS_RESOLVED = 'resolved';         // 已解决
    public const STATUS_CLOSED = 'closed';             // 已关闭

    // 处理方式
    public const RESOLUTION_ACCEPT = 'accept';               // 接受（按实收入库）
    public const RESOLUTION_REJECT = 'reject';               // 拒收退回
    public const RESOLUTION_CLAIM = 'claim';                 // 理赔
    public const RESOLUTION_RECOUNT = 'recount';             // 重新清点
    public const RESOLUTION_PARTIAL_ACCEPT = 'partial_accept'; // 部分接受

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(length: 32, unique: true)]
    private string $exceptionNo;  // 异常单号，如：EX20231217000001

    #[ORM\ManyToOne(targetEntity: InboundOrder::class, inversedBy: 'exceptions')]
    #[ORM\JoinColumn(nullable: false)]
    private InboundOrder $inboundOrder;

    #[ORM\ManyToOne(targetEntity: Merchant::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Merchant $merchant;

    #[ORM\ManyToOne(targetEntity: Warehouse::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Warehouse $warehouse;

    #[ORM\Column(length: 30)]
    private string $type;  // 异常类型

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    // 异常明细（JSON 存储）
    #[ORM\Column(type: 'json')]
    private array $items = [];  // 异常明细，如：[{"sku_id": "xxx", "expected": 100, "actual": 90, "issue": "短少10件"}]

    // 数量汇总
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $totalExpectedQuantity = 0;  // 预报总数

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $totalActualQuantity = 0;  // 实际总数

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $differenceQuantity = 0;  // 差异数量（绝对值）

    // 描述和证据
    #[ORM\Column(type: 'text')]
    private string $description;  // 异常描述

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $evidenceImages = null;  // 证据图片 URL 列表

    // 处理信息
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $resolution = null;  // 处理方式

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resolutionNotes = null;  // 处理说明

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $claimAmount = null;  // 理赔金额

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;  // 解决时间

    // 沟通记录
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $communicationLog = null;  // 沟通记录，如：[{"time": "...", "from": "merchant", "content": "..."}]

    // 操作人
    #[ORM\Column(length: 26, nullable: true)]
    private ?string $reportedBy = null;  // 上报人（仓库操作员）

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $resolvedBy = null;  // 处理人

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

    public function getExceptionNo(): string
    {
        return $this->exceptionNo;
    }

    public function setExceptionNo(string $exceptionNo): static
    {
        $this->exceptionNo = $exceptionNo;
        return $this;
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
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

    public function getItems(): array
    {
        return $this->items;
    }

    public function setItems(array $items): static
    {
        $this->items = $items;
        return $this;
    }

    public function getTotalExpectedQuantity(): int
    {
        return $this->totalExpectedQuantity;
    }

    public function setTotalExpectedQuantity(int $totalExpectedQuantity): static
    {
        $this->totalExpectedQuantity = $totalExpectedQuantity;
        return $this;
    }

    public function getTotalActualQuantity(): int
    {
        return $this->totalActualQuantity;
    }

    public function setTotalActualQuantity(int $totalActualQuantity): static
    {
        $this->totalActualQuantity = $totalActualQuantity;
        return $this;
    }

    public function getDifferenceQuantity(): int
    {
        return $this->differenceQuantity;
    }

    public function setDifferenceQuantity(int $differenceQuantity): static
    {
        $this->differenceQuantity = $differenceQuantity;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getEvidenceImages(): ?array
    {
        return $this->evidenceImages;
    }

    public function setEvidenceImages(?array $evidenceImages): static
    {
        $this->evidenceImages = $evidenceImages;
        return $this;
    }

    public function getResolution(): ?string
    {
        return $this->resolution;
    }

    public function setResolution(?string $resolution): static
    {
        $this->resolution = $resolution;
        return $this;
    }

    public function getResolutionNotes(): ?string
    {
        return $this->resolutionNotes;
    }

    public function setResolutionNotes(?string $resolutionNotes): static
    {
        $this->resolutionNotes = $resolutionNotes;
        return $this;
    }

    public function getClaimAmount(): ?string
    {
        return $this->claimAmount;
    }

    public function setClaimAmount(?string $claimAmount): static
    {
        $this->claimAmount = $claimAmount;
        return $this;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): static
    {
        $this->resolvedAt = $resolvedAt;
        return $this;
    }

    public function getCommunicationLog(): ?array
    {
        return $this->communicationLog;
    }

    public function setCommunicationLog(?array $communicationLog): static
    {
        $this->communicationLog = $communicationLog;
        return $this;
    }

    public function getReportedBy(): ?string
    {
        return $this->reportedBy;
    }

    public function setReportedBy(?string $reportedBy): static
    {
        $this->reportedBy = $reportedBy;
        return $this;
    }

    public function getResolvedBy(): ?string
    {
        return $this->resolvedBy;
    }

    public function setResolvedBy(?string $resolvedBy): static
    {
        $this->resolvedBy = $resolvedBy;
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

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * 添加沟通记录
     */
    public function addCommunication(string $from, string $content): void
    {
        $log = $this->communicationLog ?? [];
        $log[] = [
            'time' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'from' => $from,
            'content' => $content,
        ];
        $this->communicationLog = $log;
    }

    /**
     * 解决异常
     */
    public function resolve(string $resolution, ?string $notes = null, ?string $resolvedBy = null): void
    {
        $this->resolution = $resolution;
        $this->resolutionNotes = $notes;
        $this->resolvedBy = $resolvedBy;
        $this->resolvedAt = new \DateTimeImmutable();
        $this->status = self::STATUS_RESOLVED;
    }

    /**
     * 获取类型标签
     */
    public function getTypeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_QUANTITY_SHORT => '数量短少',
            self::TYPE_QUANTITY_OVER => '数量超出',
            self::TYPE_DAMAGED => '货物损坏',
            self::TYPE_WRONG_ITEM => '货物错误',
            self::TYPE_QUALITY_ISSUE => '质量问题',
            self::TYPE_PACKAGING => '包装问题',
            self::TYPE_EXPIRED => '过期/临期',
            self::TYPE_OTHER => '其他',
            default => $this->type,
        };
    }

    /**
     * 生成异常单号
     */
    public static function generateExceptionNo(): string
    {
        return 'EX' . date('Ymd') . strtoupper(substr((string) new Ulid(), -8));
    }

    /**
     * 从入库单明细生成异常项
     */
    public static function createFromInboundItems(
        InboundOrder $order,
        array $discrepancyItems,
        string $type,
        string $description
    ): self {
        $exception = new self();
        $exception->exceptionNo = self::generateExceptionNo();
        $exception->inboundOrder = $order;
        $exception->merchant = $order->getMerchant();
        $exception->warehouse = $order->getWarehouse();
        $exception->type = $type;
        $exception->description = $description;
        $exception->items = $discrepancyItems;

        // 计算数量汇总
        $totalExpected = 0;
        $totalActual = 0;
        foreach ($discrepancyItems as $item) {
            $totalExpected += $item['expected'] ?? 0;
            $totalActual += $item['actual'] ?? 0;
        }
        $exception->totalExpectedQuantity = $totalExpected;
        $exception->totalActualQuantity = $totalActual;
        $exception->differenceQuantity = abs($totalExpected - $totalActual);

        return $exception;
    }
}
