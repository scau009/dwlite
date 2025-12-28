<?php

namespace App\Entity;

use App\Repository\WalletRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: WalletRepository::class)]
#[ORM\Table(name: 'wallets')]
#[ORM\HasLifecycleCallbacks]
class Wallet
{
    // 钱包类型常量
    public const TYPE_DEPOSIT = 'deposit';   // 保证金钱包（不可提现）
    public const TYPE_BALANCE = 'balance';   // 余额钱包（可提现）

    // 钱包状态常量
    public const STATUS_ACTIVE = 'active';     // 正常
    public const STATUS_FROZEN = 'frozen';     // 冻结

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Merchant::class, inversedBy: 'wallets')]
    #[ORM\JoinColumn(name: 'merchant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Merchant $merchant;

    #[ORM\Column(type: 'string', length: 20)]
    private string $type;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $balance = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $frozenAmount = '0.00';

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\OneToMany(targetEntity: WalletTransaction::class, mappedBy: 'wallet', cascade: ['persist'])]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $transactions;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->transactions = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): string
    {
        return $this->id;
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getBalance(): string
    {
        return $this->balance;
    }

    public function setBalance(string $balance): static
    {
        $this->balance = $balance;
        return $this;
    }

    public function getFrozenAmount(): string
    {
        return $this->frozenAmount;
    }

    public function setFrozenAmount(string $frozenAmount): static
    {
        $this->frozenAmount = $frozenAmount;
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

    /**
     * @return Collection<int, WalletTransaction>
     */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function addTransaction(WalletTransaction $transaction): static
    {
        if (!$this->transactions->contains($transaction)) {
            $this->transactions->add($transaction);
            $transaction->setWallet($this);
        }
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

    public function isDeposit(): bool
    {
        return $this->type === self::TYPE_DEPOSIT;
    }

    public function isBalance(): bool
    {
        return $this->type === self::TYPE_BALANCE;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isFrozen(): bool
    {
        return $this->status === self::STATUS_FROZEN;
    }

    public function canWithdraw(): bool
    {
        return $this->isBalance() && $this->isActive();
    }

    /**
     * 可用余额 = 余额 - 冻结金额
     */
    public function getAvailableBalance(): string
    {
        return bcsub($this->balance, $this->frozenAmount, 2);
    }

    /**
     * 增加余额
     */
    public function credit(string $amount): static
    {
        $this->balance = bcadd($this->balance, $amount, 2);
        return $this;
    }

    /**
     * 减少余额
     */
    public function debit(string $amount): static
    {
        if (bccomp($this->getAvailableBalance(), $amount, 2) < 0) {
            throw new \InvalidArgumentException('Insufficient available balance');
        }
        $this->balance = bcsub($this->balance, $amount, 2);
        return $this;
    }

    /**
     * 冻结金额
     */
    public function freeze(string $amount): static
    {
        if (bccomp($this->getAvailableBalance(), $amount, 2) < 0) {
            throw new \InvalidArgumentException('Insufficient available balance to freeze');
        }
        $this->frozenAmount = bcadd($this->frozenAmount, $amount, 2);
        return $this;
    }

    /**
     * 解冻金额
     */
    public function unfreeze(string $amount): static
    {
        if (bccomp($this->frozenAmount, $amount, 2) < 0) {
            throw new \InvalidArgumentException('Unfreeze amount exceeds frozen amount');
        }
        $this->frozenAmount = bcsub($this->frozenAmount, $amount, 2);
        return $this;
    }
}