<?php

namespace App\Entity;

use App\Repository\WarehouseRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: WarehouseRepository::class)]
#[ORM\Table(name: 'warehouses')]
#[ORM\Index(name: 'idx_warehouse_code', columns: ['code'])]
#[ORM\Index(name: 'idx_warehouse_status', columns: ['status'])]
#[ORM\Index(name: 'idx_warehouse_type', columns: ['type'])]
#[ORM\Index(name: 'idx_warehouse_category', columns: ['category'])]
#[ORM\Index(name: 'idx_warehouse_merchant', columns: ['merchant_id'])]
#[ORM\Index(name: 'idx_warehouse_country', columns: ['countryCode'])]
#[ORM\HasLifecycleCallbacks]
class Warehouse
{
    // 仓库类型（物理类型/运营模式）
    public const TYPE_SELF = 'self';               // 自营仓
    public const TYPE_THIRD_PARTY = 'third_party'; // 第三方仓
    public const TYPE_BONDED = 'bonded';           // 保税仓
    public const TYPE_OVERSEAS = 'overseas';       // 海外仓

    // 仓库分类（所有权）
    public const CATEGORY_PLATFORM = 'platform';   // 平台仓库（送仓模式）
    public const CATEGORY_MERCHANT = 'merchant';   // 商家自有仓库（不送仓模式）

    // 仓库状态
    public const STATUS_ACTIVE = 'active';             // 正常运营
    public const STATUS_MAINTENANCE = 'maintenance';   // 维护中
    public const STATUS_DISABLED = 'disabled';         // 已停用

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(length: 50, unique: true)]
    private string $code;  // 仓库编码，如：WH-SH-001

    #[ORM\Column(length: 100)]
    private string $name;  // 仓库名称

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $shortName = null;  // 简称

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_THIRD_PARTY;

    #[ORM\Column(length: 20, options: ['default' => 'platform'])]
    private string $category = self::CATEGORY_PLATFORM;

    #[ORM\ManyToOne(targetEntity: Merchant::class)]
    #[ORM\JoinColumn(name: 'merchant_id', nullable: true, onDelete: 'CASCADE')]
    private ?Merchant $merchant = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    // 国家/地区信息
    #[ORM\Column(length: 2, options: ['default' => 'CN'])]
    private string $countryCode = 'CN';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $timezone = null;

    // 地址信息
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $province = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $district = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    // 联系方式
    #[ORM\Column(length: 50)]
    private string $contactName = '';

    #[ORM\Column(length: 20)]
    private string $contactPhone = '';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $contactEmail = null;

    // 备注
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $internalNotes = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

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

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): static
    {
        $this->shortName = $shortName;
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

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getMerchant(): ?Merchant
    {
        return $this->merchant;
    }

    public function setMerchant(?Merchant $merchant): static
    {
        $this->merchant = $merchant;
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

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $countryCode): static
    {
        $this->countryCode = $countryCode;
        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): static
    {
        $this->timezone = $timezone;
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

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;
        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): static
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): static
    {
        $this->latitude = $latitude;
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

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): static
    {
        $this->contactEmail = $contactEmail;
        return $this;
    }

    public function getInternalNotes(): ?string
    {
        return $this->internalNotes;
    }

    public function setInternalNotes(?string $internalNotes): static
    {
        $this->internalNotes = $internalNotes;
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

    public function getFullAddress(): string
    {
        return ($this->province ?? '') . ($this->city ?? '') . ($this->district ?? '') . ($this->address ?? '');
    }

    public function isSelfOperated(): bool
    {
        return $this->type === self::TYPE_SELF;
    }

    public function isThirdParty(): bool
    {
        return $this->type === self::TYPE_THIRD_PARTY;
    }

    public function isBonded(): bool
    {
        return $this->type === self::TYPE_BONDED;
    }

    public function isOverseas(): bool
    {
        return $this->type === self::TYPE_OVERSEAS;
    }

    public function isDomestic(): bool
    {
        return $this->countryCode === 'CN';
    }

    public function isPlatformWarehouse(): bool
    {
        return $this->category === self::CATEGORY_PLATFORM;
    }

    public function isMerchantWarehouse(): bool
    {
        return $this->category === self::CATEGORY_MERCHANT;
    }

    public static function createMerchantWarehouse(Merchant $merchant, string $code, string $name): static
    {
        $warehouse = new static();
        $warehouse->setCategory(self::CATEGORY_MERCHANT);
        $warehouse->setMerchant($merchant);
        $warehouse->setCode($code);
        $warehouse->setName($name);
        $warehouse->setType(self::TYPE_SELF);
        return $warehouse;
    }
}
