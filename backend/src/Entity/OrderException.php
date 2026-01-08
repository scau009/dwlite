<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrderExceptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 订单异常工单 - 订单校验失败时创建的异常记录.
 */
#[ORM\Entity(repositoryClass: OrderExceptionRepository::class)]
#[ORM\Table(name: 'order_exceptions')]
#[ORM\Index(name: 'idx_order_exc_order', columns: ['order_id'])]
#[ORM\Index(name: 'idx_order_exc_status', columns: ['status'])]
#[ORM\Index(name: 'idx_order_exc_type', columns: ['type'])]
#[ORM\Index(name: 'idx_order_exc_created', columns: ['created_at'])]
#[ORM\HasLifecycleCallbacks]
class OrderException
{
    // 异常类型
    public const TYPE_INVENTORY_INSUFFICIENT = 'inventory_insufficient';  // 平台库存不足
    public const TYPE_PRICE_BELOW_PLATFORM = 'price_below_platform';      // 价格低于平台价
    public const TYPE_PRODUCT_NOT_MATCHED = 'product_not_matched';        // 商品未匹配
    public const TYPE_NO_MERCHANT_AVAILABLE = 'no_merchant_available';    // 无商户可履约
    public const TYPE_ALLOCATION_EXHAUSTED = 'allocation_exhausted';      // 所有商户已尝试
    public const TYPE_OTHER = 'other';                                    // 其他

    // 状态
    public const STATUS_PENDING = 'pending';       // 待处理
    public const STATUS_RESOLVED = 'resolved';     // 已解决
    public const STATUS_CLOSED = 'closed';         // 已关闭

    // 处理方式
    public const RESOLUTION_CONFIRM = 'confirm';   // 确认订单（忽略异常）
    public const RESOLUTION_CANCEL = 'cancel';     // 取消订单
    public const RESOLUTION_ADJUSTED = 'adjusted'; // 已调整（补库存/改价格）

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(length: 32, unique: true)]
    private string $exceptionNo;  // 异常单号，如：OE20231217000001

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Order $order;

    #[ORM\Column(length: 30)]
    private string $type;  // 异常类型

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'text')]
    private string $description;  // 异常描述

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $details = null;  // 异常详情（如：{skuId, required, available}）

    // 处理信息
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $resolution = null;  // 处理方式

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resolutionNotes = null;  // 处理说明

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $resolvedBy = null;  // 处理人

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;  // 处理时间

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

    public function getExceptionNo(): string
    {
        return $this->exceptionNo;
    }

    public function setExceptionNo(string $exceptionNo): static
    {
        $this->exceptionNo = $exceptionNo;

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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function setDetails(?array $details): static
    {
        $this->details = $details;

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

    public function getResolvedBy(): ?string
    {
        return $this->resolvedBy;
    }

    public function setResolvedBy(?string $resolvedBy): static
    {
        $this->resolvedBy = $resolvedBy;

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
     * 解决异常.
     */
    public function resolve(string $resolution, ?string $notes = null, ?string $resolvedBy = null): void
    {
        $this->resolution = $resolution;
        $this->resolutionNotes = $notes;
        $this->resolvedBy = $resolvedBy;
        $this->resolvedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->status = self::STATUS_RESOLVED;
    }

    /**
     * 关闭异常.
     */
    public function close(): void
    {
        $this->status = self::STATUS_CLOSED;
    }

    /**
     * 获取类型标签.
     */
    public function getTypeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_INVENTORY_INSUFFICIENT => '平台库存不足',
            self::TYPE_PRICE_BELOW_PLATFORM => '价格低于平台价',
            self::TYPE_PRODUCT_NOT_MATCHED => '商品未匹配',
            self::TYPE_OTHER => '其他',
            default => $this->type,
        };
    }

    /**
     * 获取状态标签.
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => '待处理',
            self::STATUS_RESOLVED => '已解决',
            self::STATUS_CLOSED => '已关闭',
            default => $this->status,
        };
    }

    /**
     * 获取处理方式标签.
     */
    public function getResolutionLabel(): ?string
    {
        if ($this->resolution === null) {
            return null;
        }

        return match ($this->resolution) {
            self::RESOLUTION_CONFIRM => '确认订单',
            self::RESOLUTION_CANCEL => '取消订单',
            self::RESOLUTION_ADJUSTED => '已调整',
            default => $this->resolution,
        };
    }

    /**
     * 生成异常单号.
     */
    public static function generateExceptionNo(): string
    {
        return 'OE'.date('Ymd').strtoupper(substr((string) new Ulid(), -8));
    }

    /**
     * 从订单创建异常单.
     */
    public static function createForOrder(
        Order $order,
        string $type,
        string $description,
        ?array $details = null
    ): self {
        $exception = new self();
        $exception->exceptionNo = self::generateExceptionNo();
        $exception->order = $order;
        $exception->type = $type;
        $exception->description = $description;
        $exception->details = $details;

        return $exception;
    }

    /**
     * 获取所有类型选项.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function getTypeOptions(): array
    {
        return [
            ['value' => self::TYPE_INVENTORY_INSUFFICIENT, 'label' => '平台库存不足'],
            ['value' => self::TYPE_PRICE_BELOW_PLATFORM, 'label' => '价格低于平台价'],
            ['value' => self::TYPE_PRODUCT_NOT_MATCHED, 'label' => '商品未匹配'],
            ['value' => self::TYPE_OTHER, 'label' => '其他'],
        ];
    }

    /**
     * 获取所有处理方式选项.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function getResolutionOptions(): array
    {
        return [
            ['value' => self::RESOLUTION_CONFIRM, 'label' => '确认订单（忽略异常）'],
            ['value' => self::RESOLUTION_CANCEL, 'label' => '取消订单'],
            ['value' => self::RESOLUTION_ADJUSTED, 'label' => '已调整（补库存/改价格）'],
        ];
    }
}
