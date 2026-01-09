<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MerchantBankAccountRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * MerchantBankAccount - 商户银行账户，用于提现.
 */
#[ORM\Entity(repositoryClass: MerchantBankAccountRepository::class)]
#[ORM\Table(name: 'merchant_bank_accounts')]
#[ORM\Index(name: 'idx_mba_merchant', columns: ['merchant_id'])]
#[ORM\Index(name: 'idx_mba_default', columns: ['merchant_id', 'is_default'])]
#[ORM\HasLifecycleCallbacks]
class MerchantBankAccount
{
    // 账户类型
    public const TYPE_CORPORATE = 'corporate';  // 对公账户
    public const TYPE_PERSONAL = 'personal';    // 对私账户

    // 状态
    public const STATUS_ACTIVE = 'active';      // 可用
    public const STATUS_PENDING = 'pending';    // 待验证
    public const STATUS_DISABLED = 'disabled';  // 已禁用

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Merchant::class)]
    #[ORM\JoinColumn(name: 'merchant_id', nullable: false, onDelete: 'CASCADE')]
    private Merchant $merchant;

    #[ORM\Column(type: 'string', length: 100)]
    private string $bankName;  // 银行名称

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $bankCode = null;  // 银行代码（如 SWIFT）

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    private ?string $branchName = null;  // 支行名称

    #[ORM\Column(type: 'string', length: 50)]
    private string $accountNumber;  // 银行账号

    #[ORM\Column(type: 'string', length: 100)]
    private string $accountHolder;  // 户名

    #[ORM\Column(type: 'string', length: 20)]
    private string $accountType = self::TYPE_CORPORATE;

    #[ORM\Column(type: 'string', length: 3, options: ['default' => 'CNY'])]
    private string $currency = 'CNY';

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isDefault = false;  // 是否默认账户

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

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

    public function getMerchant(): Merchant
    {
        return $this->merchant;
    }

    public function setMerchant(Merchant $merchant): static
    {
        $this->merchant = $merchant;

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

    public function getBankCode(): ?string
    {
        return $this->bankCode;
    }

    public function setBankCode(?string $bankCode): static
    {
        $this->bankCode = $bankCode;

        return $this;
    }

    public function getBranchName(): ?string
    {
        return $this->branchName;
    }

    public function setBranchName(?string $branchName): static
    {
        $this->branchName = $branchName;

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

    public function getAccountType(): string
    {
        return $this->accountType;
    }

    public function setAccountType(string $accountType): static
    {
        $this->accountType = $accountType;

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

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
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

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isDisabled(): bool
    {
        return $this->status === self::STATUS_DISABLED;
    }

    public function isCorporate(): bool
    {
        return $this->accountType === self::TYPE_CORPORATE;
    }

    public function isPersonal(): bool
    {
        return $this->accountType === self::TYPE_PERSONAL;
    }

    public function canBeUsedForPayout(): bool
    {
        return $this->isActive();
    }

    /**
     * 验证银行账户.
     */
    public function verify(): void
    {
        $this->status = self::STATUS_ACTIVE;
        $this->verifiedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * 禁用银行账户.
     */
    public function disable(): void
    {
        $this->status = self::STATUS_DISABLED;
        $this->isDefault = false;
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
