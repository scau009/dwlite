<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PayoutRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Payout - 商户提现申请.
 */
#[ORM\Entity(repositoryClass: PayoutRepository::class)]
#[ORM\Table(name: 'payouts')]
#[ORM\Index(name: 'idx_payout_merchant', columns: ['merchant_id'])]
#[ORM\Index(name: 'idx_payout_status', columns: ['status'])]
#[ORM\Index(name: 'idx_payout_no', columns: ['payout_no'])]
#[ORM\Index(name: 'idx_payout_bank_account', columns: ['bank_account_id'])]
#[ORM\HasLifecycleCallbacks]
class Payout
{
    // 提现状态
    public const STATUS_PENDING = 'pending';         // 待审核
    public const STATUS_APPROVED = 'approved';       // 已批准，待处理
    public const STATUS_PROCESSING = 'processing';   // 处理中（银行转账中）
    public const STATUS_COMPLETED = 'completed';     // 已完成
    public const STATUS_REJECTED = 'rejected';       // 已拒绝
    public const STATUS_FAILED = 'failed';           // 失败（如银行转账失败）

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(type: 'string', length: 30, unique: true)]
    private string $payoutNo;  // 提现单号（WD + 日期 + 序列）

    #[ORM\ManyToOne(targetEntity: Merchant::class)]
    #[ORM\JoinColumn(name: 'merchant_id', nullable: false)]
    private Merchant $merchant;

    #[ORM\ManyToOne(targetEntity: MerchantBankAccount::class)]
    #[ORM\JoinColumn(name: 'bank_account_id', nullable: false)]
    private MerchantBankAccount $bankAccount;

    // 金额字段
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $amount;  // 申请金额

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $fee = '0.00';  // 手续费

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $actualAmount;  // 实际到账（amount - fee）

    #[ORM\Column(type: 'string', length: 3, options: ['default' => 'CNY'])]
    private string $currency = 'CNY';

    // 状态
    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_PENDING;

    // 银行账户快照（防止后续修改影响历史记录）
    #[ORM\Column(type: 'string', length: 100)]
    private string $bankName;

    #[ORM\Column(type: 'string', length: 50)]
    private string $accountNumber;

    #[ORM\Column(type: 'string', length: 100)]
    private string $accountHolder;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $bankCode = null;

    // 时间字段
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $processingAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $rejectedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $failedAt = null;

    // 审核字段
    #[ORM\Column(type: 'string', length: 26, nullable: true)]
    private ?string $reviewedBy = null;  // 审核人 ID

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $rejectReason = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $failReason = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $remark = null;  // 内部备注

    // 外部交易引用
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $externalTransactionId = null;  // 银行或支付渠道的交易号

    // 钱包流水引用
    #[ORM\Column(type: 'string', length: 26, nullable: true)]
    private ?string $walletTransactionId = null;  // 扣款流水 ID

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

    public function getPayoutNo(): string
    {
        return $this->payoutNo;
    }

    public function setPayoutNo(string $payoutNo): static
    {
        $this->payoutNo = $payoutNo;

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

    public function getBankAccount(): MerchantBankAccount
    {
        return $this->bankAccount;
    }

    public function setBankAccount(MerchantBankAccount $bankAccount): static
    {
        $this->bankAccount = $bankAccount;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getFee(): string
    {
        return $this->fee;
    }

    public function setFee(string $fee): static
    {
        $this->fee = $fee;

        return $this;
    }

    public function getActualAmount(): string
    {
        return $this->actualAmount;
    }

    public function setActualAmount(string $actualAmount): static
    {
        $this->actualAmount = $actualAmount;

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

    public function getBankName(): string
    {
        return $this->bankName;
    }

    public function setBankName(string $bankName): static
    {
        $this->bankName = $bankName;

        return $this;
    }

    public function getAccountNumber(): string
    {
        return $this->accountNumber;
    }

    public function setAccountNumber(string $accountNumber): static
    {
        $this->accountNumber = $accountNumber;

        return $this;
    }

    public function getAccountHolder(): string
    {
        return $this->accountHolder;
    }

    public function setAccountHolder(string $accountHolder): static
    {
        $this->accountHolder = $accountHolder;

        return $this;
    }

    public function getBankCode(): ?string
    {
        return $this->bankCode;
    }

    public function setBankCode(?string $bankCode): static
    {
        $this->bankCode = $bankCode;

        return $this;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function getProcessingAt(): ?\DateTimeImmutable
    {
        return $this->processingAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getRejectedAt(): ?\DateTimeImmutable
    {
        return $this->rejectedAt;
    }

    public function getFailedAt(): ?\DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function getReviewedBy(): ?string
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?string $reviewedBy): static
    {
        $this->reviewedBy = $reviewedBy;

        return $this;
    }

    public function getRejectReason(): ?string
    {
        return $this->rejectReason;
    }

    public function getFailReason(): ?string
    {
        return $this->failReason;
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

    public function getExternalTransactionId(): ?string
    {
        return $this->externalTransactionId;
    }

    public function setExternalTransactionId(?string $externalTransactionId): static
    {
        $this->externalTransactionId = $externalTransactionId;

        return $this;
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

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function canApprove(): bool
    {
        return $this->isPending();
    }

    public function canReject(): bool
    {
        return $this->isPending();
    }

    public function canProcess(): bool
    {
        return $this->isApproved();
    }

    public function canComplete(): bool
    {
        return $this->isProcessing();
    }

    /**
     * 标记审核通过.
     */
    public function markApproved(string $reviewerId): void
    {
        $this->status = self::STATUS_APPROVED;
        $this->approvedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->reviewedBy = $reviewerId;
    }

    /**
     * 标记审核拒绝.
     */
    public function markRejected(string $reviewerId, string $reason): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->rejectedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->reviewedBy = $reviewerId;
        $this->rejectReason = $reason;
    }

    /**
     * 标记处理中.
     */
    public function markProcessing(string $walletTransactionId): void
    {
        $this->status = self::STATUS_PROCESSING;
        $this->processingAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->walletTransactionId = $walletTransactionId;
    }

    /**
     * 标记已完成.
     */
    public function markCompleted(?string $externalTransactionId = null): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->externalTransactionId = $externalTransactionId;
    }

    /**
     * 标记失败.
     */
    public function markFailed(string $reason): void
    {
        $this->status = self::STATUS_FAILED;
        $this->failedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->failReason = $reason;
    }

    /**
     * 快照银行账户信息.
     */
    public function snapshotBankAccount(MerchantBankAccount $account): void
    {
        $this->bankAccount = $account;
        $this->bankName = $account->getBankName();
        $this->accountNumber = $account->getAccountNumber();
        $this->accountHolder = $account->getAccountHolder();
        $this->bankCode = $account->getBankCode();
    }

    /**
     * 计算实际到账金额.
     */
    public function calculateActualAmount(): void
    {
        $this->actualAmount = bcsub($this->amount, $this->fee, 2);
    }

    /**
     * 获取脱敏账号（用于展示）.
     */
    public function getMaskedAccountNumber(): string
    {
        $length = strlen($this->accountNumber);
        if ($length <= 4) {
            return $this->accountNumber;
        }

        return str_repeat('*', $length - 4).substr($this->accountNumber, -4);
    }
}
