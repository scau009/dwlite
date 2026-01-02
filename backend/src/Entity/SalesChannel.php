<?php

namespace App\Entity;

use App\Repository\SalesChannelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: SalesChannelRepository::class)]
#[ORM\Table(name: 'sales_channels')]
#[ORM\Index(name: 'idx_channel_code', columns: ['code'])]
#[ORM\HasLifecycleCallbacks]
class SalesChannel
{
    // 业务类型
    public const BUSINESS_TYPE_IMPORT = 'import';  // 进口
    public const BUSINESS_TYPE_EXPORT = 'export';  // 出口

    // 渠道状态
    public const STATUS_ACTIVE = 'active';       // 正常
    public const STATUS_MAINTENANCE = 'maintenance'; // 维护中
    public const STATUS_DISABLED = 'disabled';   // 已禁用

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(length: 50, unique: true)]
    private string $code;  // 渠道编码，如：taobao, jd, douyin, wechat

    #[ORM\Column(length: 100)]
    private string $name;  // 渠道名称，如：淘宝、京东、抖音、微信小程序

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $logoUrl = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $config = null;  // 渠道全局配置

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $configSchema = null;  // 商户配置的 JSON Schema，定义商户需要填写的字段

    #[ORM\Column(type: 'string', length: 20)]
    private string $businessType = self::BUSINESS_TYPE_EXPORT;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\OneToMany(targetEntity: MerchantSalesChannel::class, mappedBy: 'salesChannel', cascade: ['persist', 'remove'])]
    private Collection $merchantChannels;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->merchantChannels = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

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

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function setLogoUrl(?string $logoUrl): static
    {
        $this->logoUrl = $logoUrl;

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

    public function getConfigSchema(): ?array
    {
        return $this->configSchema;
    }

    public function setConfigSchema(?array $configSchema): static
    {
        $this->configSchema = $configSchema;

        return $this;
    }

    public function getBusinessType(): string
    {
        return $this->businessType;
    }

    public function setBusinessType(string $businessType): static
    {
        $this->businessType = $businessType;

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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    /**
     * @return Collection<int, MerchantSalesChannel>
     */
    public function getMerchantChannels(): Collection
    {
        return $this->merchantChannels;
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

    public function isMaintenance(): bool
    {
        return $this->status === self::STATUS_MAINTENANCE;
    }

    public function isDisabled(): bool
    {
        return $this->status === self::STATUS_DISABLED;
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isImport(): bool
    {
        return $this->businessType === self::BUSINESS_TYPE_IMPORT;
    }

    public function isExport(): bool
    {
        return $this->businessType === self::BUSINESS_TYPE_EXPORT;
    }
}
