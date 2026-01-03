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
    // 履约模式
    public const FULFILLMENT_CONSIGNMENT = 'consignment';         // 寄售：送仓实物库存，平台履约
    public const FULFILLMENT_SELF_FULFILLMENT = 'self_fulfillment'; // 自履约：虚拟库存，商户自己发货

    public const FULFILLMENT_TYPES = [
        self::FULFILLMENT_CONSIGNMENT,
        self::FULFILLMENT_SELF_FULFILLMENT,
    ];

    // 状态
    public const STATUS_PENDING = 'pending';     // 待审核（管理员需审核开通）
    public const STATUS_ACTIVE = 'active';       // 已启用
    public const STATUS_REJECTED = 'rejected';   // 已拒绝（管理员拒绝申请）
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

    /** @var string[] 商户申请的履约模式 */
    #[ORM\Column(type: 'json')]
    private array $requestedFulfillmentTypes = [self::FULFILLMENT_CONSIGNMENT];

    /** @var string[]|null 管理员批准的履约模式 */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $approvedFulfillmentTypes = null;

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

    public function getSalesChannel(): SalesChannel
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(SalesChannel $salesChannel): static
    {
        $this->salesChannel = $salesChannel;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getRequestedFulfillmentTypes(): array
    {
        return $this->requestedFulfillmentTypes;
    }

    /**
     * @param string[] $types
     */
    public function setRequestedFulfillmentTypes(array $types): static
    {
        // 验证并过滤无效值
        $this->requestedFulfillmentTypes = array_values(array_intersect($types, self::FULFILLMENT_TYPES));

        return $this;
    }

    /**
     * @return string[]|null
     */
    public function getApprovedFulfillmentTypes(): ?array
    {
        return $this->approvedFulfillmentTypes;
    }

    /**
     * @param string[]|null $types
     */
    public function setApprovedFulfillmentTypes(?array $types): static
    {
        if ($types !== null) {
            // 验证并过滤无效值
            $types = array_values(array_intersect($types, self::FULFILLMENT_TYPES));
        }
        $this->approvedFulfillmentTypes = $types;

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
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    // 状态便捷方法

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
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
     * 是否可用（需同时检查渠道本身是否可用）.
     */
    public function isAvailable(): bool
    {
        return $this->isActive() && $this->salesChannel->isAvailable();
    }

    // 履约模式便捷方法

    /**
     * 检查某个履约模式是否已申请.
     */
    public function hasRequestedFulfillmentType(string $type): bool
    {
        return in_array($type, $this->requestedFulfillmentTypes, true);
    }

    /**
     * 检查某个履约模式是否已批准.
     */
    public function hasApprovedFulfillmentType(string $type): bool
    {
        return $this->approvedFulfillmentTypes !== null
            && in_array($type, $this->approvedFulfillmentTypes, true);
    }

    /**
     * 检查是否支持寄售模式.
     */
    public function supportsConsignment(): bool
    {
        return $this->hasApprovedFulfillmentType(self::FULFILLMENT_CONSIGNMENT);
    }

    /**
     * 检查是否支持自履约模式.
     */
    public function supportsSelfFulfillment(): bool
    {
        return $this->hasApprovedFulfillmentType(self::FULFILLMENT_SELF_FULFILLMENT);
    }

    // 状态操作方法

    /**
     * 管理员审核通过.
     *
     * @param string        $adminId       审核人ID
     * @param string[]|null $approvedTypes 批准的履约模式，null 表示全部批准
     */
    public function approve(string $adminId, ?array $approvedTypes = null): static
    {
        $this->status = self::STATUS_ACTIVE;
        $this->approvedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->approvedBy = $adminId;
        $this->remark = null;

        // 如果未指定，则批准所有申请的模式
        if ($approvedTypes === null) {
            $this->approvedFulfillmentTypes = $this->requestedFulfillmentTypes;
        } else {
            // 只能批准商户申请过的模式
            $this->approvedFulfillmentTypes = array_values(
                array_intersect($approvedTypes, $this->requestedFulfillmentTypes)
            );
        }

        return $this;
    }

    /**
     * 管理员拒绝申请.
     */
    public function reject(?string $reason = null): static
    {
        $this->status = self::STATUS_REJECTED;
        $this->remark = $reason;
        $this->approvedFulfillmentTypes = null;

        return $this;
    }

    /**
     * 管理员暂停.
     */
    public function suspend(?string $reason = null): static
    {
        $this->status = self::STATUS_SUSPENDED;
        $this->remark = $reason;

        return $this;
    }

    /**
     * 商户自行禁用.
     */
    public function disable(): static
    {
        $this->status = self::STATUS_DISABLED;

        return $this;
    }

    /**
     * 重新启用.
     */
    public function enable(): static
    {
        $this->status = self::STATUS_ACTIVE;

        return $this;
    }

    /**
     * 更新已批准的履约模式（管理员操作）.
     *
     * @param string[] $types
     */
    public function updateApprovedFulfillmentTypes(array $types): static
    {
        // 只能批准商户申请过的模式
        $this->approvedFulfillmentTypes = array_values(
            array_intersect($types, $this->requestedFulfillmentTypes)
        );

        return $this;
    }
}