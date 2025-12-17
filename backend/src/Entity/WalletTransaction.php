<?php

namespace App\Entity;

use App\Repository\WalletTransactionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: WalletTransactionRepository::class)]
#[ORM\Table(name: 'wallet_transactions')]
#[ORM\Index(name: 'idx_wallet_created', columns: ['wallet_id', 'created_at'])]
#[ORM\Index(name: 'idx_biz', columns: ['biz_type', 'biz_id'])]
#[ORM\HasLifecycleCallbacks]
class WalletTransaction
{
    // 交易类型常量
    public const TYPE_CREDIT = 'credit';   // 入账
    public const TYPE_DEBIT = 'debit';     // 出账
    public const TYPE_FREEZE = 'freeze';   // 冻结
    public const TYPE_UNFREEZE = 'unfreeze'; // 解冻

    // 业务类型常量
    public const BIZ_DEPOSIT_CHARGE = 'deposit_charge';     // 保证金充值
    public const BIZ_DEPOSIT_DEDUCT = 'deposit_deduct';     // 保证金扣除
    public const BIZ_ORDER_INCOME = 'order_income';         // 订单收入
    public const BIZ_WITHDRAW = 'withdraw';                 // 提现
    public const BIZ_WITHDRAW_REJECT = 'withdraw_reject';   // 提现拒绝退回
    public const BIZ_REFUND = 'refund';                     // 退款
    public const BIZ_PLATFORM_FEE = 'platform_fee';         // 平台服务费
    public const BIZ_ADJUSTMENT = 'adjustment';             // 调账

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Wallet::class, inversedBy: 'transactions')]
    #[ORM\JoinColumn(name: 'wallet_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Wallet $wallet;

    #[ORM\Column(type: 'string', length: 20)]
    private string $type;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $amount;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $balanceBefore;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $balanceAfter;

    #[ORM\Column(type: 'string', length: 50)]
    private string $bizType;

    #[ORM\Column(type: 'string', length: 26, nullable: true)]
    private ?string $bizId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $remark = null;

    #[ORM\Column(type: 'string', length: 26, nullable: true)]
    private ?string $operatorId = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getWallet(): Wallet
    {
        return $this->wallet;
    }

    public function setWallet(Wallet $wallet): static
    {
        $this->wallet = $wallet;
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

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getBalanceBefore(): string
    {
        return $this->balanceBefore;
    }

    public function setBalanceBefore(string $balanceBefore): static
    {
        $this->balanceBefore = $balanceBefore;
        return $this;
    }

    public function getBalanceAfter(): string
    {
        return $this->balanceAfter;
    }

    public function setBalanceAfter(string $balanceAfter): static
    {
        $this->balanceAfter = $balanceAfter;
        return $this;
    }

    public function getBizType(): string
    {
        return $this->bizType;
    }

    public function setBizType(string $bizType): static
    {
        $this->bizType = $bizType;
        return $this;
    }

    public function getBizId(): ?string
    {
        return $this->bizId;
    }

    public function setBizId(?string $bizId): static
    {
        $this->bizId = $bizId;
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

    public function getOperatorId(): ?string
    {
        return $this->operatorId;
    }

    public function setOperatorId(?string $operatorId): static
    {
        $this->operatorId = $operatorId;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    // 便捷方法

    public function isCredit(): bool
    {
        return $this->type === self::TYPE_CREDIT;
    }

    public function isDebit(): bool
    {
        return $this->type === self::TYPE_DEBIT;
    }
}