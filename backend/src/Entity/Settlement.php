<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SettlementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Settlement - T+N 延迟结算单，履约单完成后创建.
 */
#[ORM\Entity(repositoryClass: SettlementRepository::class)]
#[ORM\Table(name: 'settlements')]
#[ORM\Index(name: 'idx_settlement_merchant', columns: ['merchant_id'])]
#[ORM\Index(name: 'idx_settlement_fulfillment', columns: ['fulfillment_id'])]
#[ORM\Index(name: 'idx_settlement_status', columns: ['status'])]
#[ORM\Index(name: 'idx_settlement_scheduled', columns: ['scheduled_settle_at'])]
#[ORM\Index(name: 'idx_settlement_no', columns: ['settlement_no'])]
#[ORM\HasLifecycleCallbacks]
class Settlement
{
    // 结算状态
    public const STATUS_PENDING = 'pending';       // 待结算（T+N 期间）
    public const STATUS_SETTLED = 'settled';       // 已结算（已入账到钱包）
    public const STATUS_CANCELLED = 'cancelled';   // 已取消（如订单退款）

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(type: 'string', length: 30, unique: true)]
    private string $settlementNo;  // 结算单号（ST + 日期 + 序列）

    #[ORM\ManyToOne(targetEntity: Merchant::class)]
    #[ORM\JoinColumn(name: 'merchant_id', nullable: false)]
    private Merchant $merchant;

    #[ORM\OneToOne(targetEntity: Fulfillment::class)]
    #[ORM\JoinColumn(name: 'fulfillment_id', nullable: false, unique: true)]
    private Fulfillment $fulfillment;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(name: 'order_id', nullable: false)]
    private Order $order;

    // 金额字段（从 FulfillmentItems 快照）
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $grossAmount;  // 结算总额（扣佣前）

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $commissionRate;  // 佣金比例（如 5.00 表示 5%）

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $commissionAmount;  // 佣金金额

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $netAmount;  // 实际到账（gross - commission）

    #[ORM\Column(type: 'string', length: 3, options: ['default' => 'CNY'])]
    private string $currency = 'CNY';

    // 状态
    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_PENDING;

    // T+N 配置
    #[ORM\Column(type: 'integer', options: ['default' => 7])]
    private int $settlementDays = 7;  // N 天延迟（可配置）

    // 时间字段
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $scheduledSettleAt;  // 预计结算时间

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $settledAt = null;  // 实际结算时间

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;  // 取消时间

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $cancelReason = null;  // 取消原因

    // 钱包流水引用
    #[ORM\Column(type: 'string', length: 26, nullable: true)]
    private ?string $walletTransactionId = null;

    // 结算明细
    /** @var Collection<int, SettlementItem> */
    #[ORM\OneToMany(targetEntity: SettlementItem::class, mappedBy: 'settlement', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSettlementNo(): string
    {
        return $this->settlementNo;
    }

    public function setSettlementNo(string $settlementNo): static
    {
        $this->settlementNo = $settlementNo;

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

    public function getFulfillment(): Fulfillment
    {
        return $this->fulfillment;
    }

    public function setFulfillment(Fulfillment $fulfillment): static
    {
        $this->fulfillment = $fulfillment;

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

    public function getGrossAmount(): string
    {
        return $this->grossAmount;
    }

    public function setGrossAmount(string $grossAmount): static
    {
        $this->grossAmount = $grossAmount;

        return $this;
    }

    public function getCommissionRate(): string
    {
        return $this->commissionRate;
    }

    public function setCommissionRate(string $commissionRate): static
    {
        $this->commissionRate = $commissionRate;

        return $this;
    }

    public function getCommissionAmount(): string
    {
        return $this->commissionAmount;
    }

    public function setCommissionAmount(string $commissionAmount): static
    {
        $this->commissionAmount = $commissionAmount;

        return $this;
    }

    public function getNetAmount(): string
    {
        return $this->netAmount;
    }

    public function setNetAmount(string $netAmount): static
    {
        $this->netAmount = $netAmount;

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

    public function getSettlementDays(): int
    {
        return $this->settlementDays;
    }

    public function setSettlementDays(int $settlementDays): static
    {
        $this->settlementDays = $settlementDays;

        return $this;
    }

    public function getScheduledSettleAt(): \DateTimeImmutable
    {
        return $this->scheduledSettleAt;
    }

    public function setScheduledSettleAt(\DateTimeImmutable $scheduledSettleAt): static
    {
        $this->scheduledSettleAt = $scheduledSettleAt;

        return $this;
    }

    public function getSettledAt(): ?\DateTimeImmutable
    {
        return $this->settledAt;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function getCancelReason(): ?string
    {
        return $this->cancelReason;
    }

    public function getWalletTransactionId(): ?string
    {
        return $this->walletTransactionId;
    }

    public function setWalletTransactionId(?string $walletTransactionId): static
    {
        $this->walletTransactionId = $walletTransactionId;

        return $this;
    }

    /**
     * @return Collection<int, SettlementItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(SettlementItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setSettlement($this);
        }

        return $this;
    }

    public function removeItem(SettlementItem $item): static
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
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    // 便捷方法

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSettled(): bool
    {
        return $this->status === self::STATUS_SETTLED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * 判断是否可以执行结算.
     */
    public function canSettle(): bool
    {
        return $this->isPending()
            && $this->scheduledSettleAt <= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * 判断是否可以取消.
     */
    public function canCancel(): bool
    {
        return $this->isPending();
    }

    /**
     * 标记已结算.
     */
    public function markSettled(string $walletTransactionId): void
    {
        $this->status = self::STATUS_SETTLED;
        $this->settledAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->walletTransactionId = $walletTransactionId;
    }

    /**
     * 标记已取消.
     */
    public function markCancelled(string $reason): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->cancelledAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->cancelReason = $reason;
    }

    /**
     * 根据完成时间计算预计结算时间.
     */
    public function calculateScheduledSettleAt(\DateTimeImmutable $completedAt): void
    {
        $this->scheduledSettleAt = $completedAt->modify(sprintf('+%d days', $this->settlementDays));
    }

    /**
     * 计算金额（从明细汇总）.
     */
    public function calculateAmounts(): void
    {
        $grossAmount = '0.00';
        $commissionAmount = '0.00';

        foreach ($this->items as $item) {
            $grossAmount = bcadd($grossAmount, $item->getGrossAmount(), 2);
            $commissionAmount = bcadd($commissionAmount, $item->getCommissionAmount(), 2);
        }

        $this->grossAmount = $grossAmount;
        $this->commissionAmount = $commissionAmount;
        $this->netAmount = bcsub($grossAmount, $commissionAmount, 2);
    }
}
