<?php

namespace App\Entity;

use App\Repository\MerchantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: MerchantRepository::class)]
#[ORM\Table(name: 'merchants')]
#[ORM\HasLifecycleCallbacks]
class Merchant
{
    // 商户状态常量
    public const STATUS_PENDING = 'pending';     // 待审核
    public const STATUS_APPROVED = 'approved';   // 已通过
    public const STATUS_REJECTED = 'rejected';   // 已拒绝
    public const STATUS_DISABLED = 'disabled';   // 已禁用

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $contactName;

    #[ORM\Column(type: 'string', length: 20)]
    private string $contactPhone;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $province = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $district = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $businessLicense = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $rejectedReason = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(targetEntity: Wallet::class, mappedBy: 'merchant', cascade: ['persist', 'remove'])]
    private Collection $wallets;

    #[ORM\OneToMany(targetEntity: MerchantSalesChannel::class, mappedBy: 'merchant', cascade: ['persist', 'remove'])]
    private Collection $salesChannels;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->wallets = new ArrayCollection();
        $this->salesChannels = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getContactName(): string
    {
        return $this->contactName;
    }

    public function setContactName(string $contactName): static
    {
        $this->contactName = $contactName;
        return $this;
    }

    public function getContactPhone(): string
    {
        return $this->contactPhone;
    }

    public function setContactPhone(string $contactPhone): static
    {
        $this->contactPhone = $contactPhone;
        return $this;
    }

    public function getProvince(): ?string
    {
        return $this->province;
    }

    public function setProvince(?string $province): static
    {
        $this->province = $province;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;
        return $this;
    }

    public function getDistrict(): ?string
    {
        return $this->district;
    }

    public function setDistrict(?string $district): static
    {
        $this->district = $district;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getBusinessLicense(): ?string
    {
        return $this->businessLicense;
    }

    public function setBusinessLicense(?string $businessLicense): static
    {
        $this->businessLicense = $businessLicense;
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

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeImmutable $approvedAt): static
    {
        $this->approvedAt = $approvedAt;
        return $this;
    }

    public function getRejectedReason(): ?string
    {
        return $this->rejectedReason;
    }

    public function setRejectedReason(?string $rejectedReason): static
    {
        $this->rejectedReason = $rejectedReason;
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

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isDisabled(): bool
    {
        return $this->status === self::STATUS_DISABLED;
    }

    public function approve(): static
    {
        $this->status = self::STATUS_APPROVED;
        $this->approvedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->rejectedReason = null;
        return $this;
    }

    public function reject(string $reason): static
    {
        $this->status = self::STATUS_REJECTED;
        $this->rejectedReason = $reason;
        $this->approvedAt = null;
        return $this;
    }

    public function disable(): static
    {
        $this->status = self::STATUS_DISABLED;
        return $this;
    }

    public function enable(): static
    {
        $this->status = self::STATUS_APPROVED;
        return $this;
    }

    public function getFullAddress(): string
    {
        return ($this->province ?? '') . ($this->city ?? '') . ($this->district ?? '') . ($this->address ?? '');
    }

    // 钱包相关方法

    /**
     * @return Collection<int, Wallet>
     */
    public function getWallets(): Collection
    {
        return $this->wallets;
    }

    public function addWallet(Wallet $wallet): static
    {
        if (!$this->wallets->contains($wallet)) {
            $this->wallets->add($wallet);
            $wallet->setMerchant($this);
        }
        return $this;
    }

    public function getDepositWallet(): ?Wallet
    {
        foreach ($this->wallets as $wallet) {
            if ($wallet->isDeposit()) {
                return $wallet;
            }
        }
        return null;
    }

    public function getBalanceWallet(): ?Wallet
    {
        foreach ($this->wallets as $wallet) {
            if ($wallet->isBalance()) {
                return $wallet;
            }
        }
        return null;
    }

    // 销售渠道相关方法

    /**
     * @return Collection<int, MerchantSalesChannel>
     */
    public function getSalesChannels(): Collection
    {
        return $this->salesChannels;
    }

    public function addSalesChannel(MerchantSalesChannel $salesChannel): static
    {
        if (!$this->salesChannels->contains($salesChannel)) {
            $this->salesChannels->add($salesChannel);
            $salesChannel->setMerchant($this);
        }
        return $this;
    }

    public function removeSalesChannel(MerchantSalesChannel $salesChannel): static
    {
        $this->salesChannels->removeElement($salesChannel);
        return $this;
    }

    /**
     * 获取已启用的销售渠道
     */
    public function getActiveSalesChannels(): array
    {
        return $this->salesChannels
            ->filter(fn(MerchantSalesChannel $mc) => $mc->isAvailable())
            ->toArray();
    }

    /**
     * 检查是否已开通某渠道
     */
    public function hasChannel(SalesChannel $channel): bool
    {
        foreach ($this->salesChannels as $mc) {
            if ($mc->getSalesChannel()->getId() === $channel->getId()) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取某渠道的关联信息
     */
    public function getChannelRelation(SalesChannel $channel): ?MerchantSalesChannel
    {
        foreach ($this->salesChannels as $mc) {
            if ($mc->getSalesChannel()->getId() === $channel->getId()) {
                return $mc;
            }
        }
        return null;
    }
}