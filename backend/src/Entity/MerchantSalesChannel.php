<?php

namespace App\Entity;

use App\Repository\MerchantSalesChannelRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: MerchantSalesChannelRepository::class)]
#[ORM\Table(name: 'merchant_sales_channels')]
#[ORM\UniqueConstraint(name: 'uk_merchant_channel', columns: ['merchant_id', 'sales_channel_id'])]
#[ORM\Index(name: 'idx_msc_merchant', columns: ['merchant_id'])]
#[ORM\Index(name: 'idx_msc_channel', columns: ['sales_channel_id'])]
#[ORM\HasLifecycleCallbacks]
class MerchantSalesChannel
{
    // 状态
    public const STATUS_PENDING = 'pending';     // 待审核（管理员需审核开通）
    public const STATUS_ACTIVE = 'active';       // 已启用
    public const STATUS_SUSPENDED = 'suspended'; // 已暂停（管理员暂停）
    public const STATUS_DISABLED = 'disabled';   // 已禁用（商户自行关闭）

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Merchant::class, inversedBy: 'salesChannels')]
    #[ORM\JoinColumn(name: 'merchant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Merchant $merchant;

    #[ORM\ManyToOne(targetEntity: SalesChannel::class, inversedBy: 'merchantChannels')]
    #[ORM\JoinColumn(name: 'sales_channel_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private SalesChannel $salesChannel;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $config = null;  // 商户针对该渠道的配置（如店铺ID、API密钥等）

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;  // 审核通过时间

    #[ORM\Column(type: 'string', length: 26, nullable: true)]
    private ?string $approvedBy = null;  // 审核人 ID

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $remark = null;  // 备注（如拒绝原因）

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

    public function getMerchant(): Merchant
    {
        return $this->merchant;
    }

    public function setMerchant(Merchant $merchant): static
    {
        $this->merchant = $merchant;
        return $this;
    }

    public function getSalesChannel(): SalesChannel
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(SalesChannel $salesChannel): static
    {
        $this->salesChannel = $salesChannel;
        return $this;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): static
    {
        $this->config = $config;
        return $this;
    }

    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function setConfigValue(string $key, mixed $value): static
    {
        if ($this->config === null) {
            $this->config = [];
        }
        $this->config[$key] = $value;
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

    public function getApprovedBy(): ?string
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?string $approvedBy): static
    {
        $this->approvedBy = $approvedBy;
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

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isDisabled(): bool
    {
        return $this->status === self::STATUS_DISABLED;
    }

    /**
     * 是否可用（需同时检查渠道本身是否可用）
     */
    public function isAvailable(): bool
    {
        return $this->isActive() && $this->salesChannel->isAvailable();
    }

    /**
     * 管理员审核通过
     */
    public function approve(string $adminId): static
    {
        $this->status = self::STATUS_ACTIVE;
        $this->approvedAt = new \DateTimeImmutable();
        $this->approvedBy = $adminId;
        $this->remark = null;
        return $this;
    }

    /**
     * 管理员暂停
     */
    public function suspend(?string $reason = null): static
    {
        $this->status = self::STATUS_SUSPENDED;
        $this->remark = $reason;
        return $this;
    }

    /**
     * 商户自行禁用
     */
    public function disable(): static
    {
        $this->status = self::STATUS_DISABLED;
        return $this;
    }

    /**
     * 重新启用
     */
    public function enable(): static
    {
        $this->status = self::STATUS_ACTIVE;
        return $this;
    }
}
