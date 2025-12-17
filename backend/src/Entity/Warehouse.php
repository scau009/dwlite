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

    // 仓库分类（所有权）
    #[ORM\Column(length: 20, options: ['default' => 'platform'])]
    private string $category = self::CATEGORY_PLATFORM;

    // 关联商家（仅商家自有仓需要）
    #[ORM\ManyToOne(targetEntity: Merchant::class)]
    #[ORM\JoinColumn(name: 'merchant_id', nullable: true, onDelete: 'CASCADE')]
    private ?Merchant $merchant = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    // 国家/地区信息（海外仓、保税仓必填）
    #[ORM\Column(length: 2, options: ['default' => 'CN'])]
    private string $countryCode = 'CN';  // ISO 3166-1 alpha-2 国家代码

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $timezone = null;  // 仓库时区，如：Asia/Shanghai

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

    // 地理坐标（用于物流计算）
    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    // 联系方式
    #[ORM\Column(length: 50)]
    private string $contactName;

    #[ORM\Column(length: 20)]
    private string $contactPhone;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $contactEmail = null;

    // 仓储能力
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $areaSquareMeters = null;  // 仓库面积（平方米）

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $storageCapacity = null;  // 仓储容量（库位数/托盘位）

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $supportedCategories = null;  // 支持的货品类目，如：["electronics", "clothing", "food"]

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $supportsColdChain = false;  // 是否支持冷链

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $supportsDangerousGoods = false;  // 是否支持危险品

    // 运营方信息
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $operatorName = null;  // 运营方/合作方公司名称

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $operatorContact = null;  // 运营方联系人

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $operatorPhone = null;  // 运营方联系电话

    // 运营时间规则
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $businessHours = null;  // 营业时间，如：{"mon-fri": "09:00-18:00", "sat": "09:00-12:00"}

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $inboundCutoffTime = null;  // 入库截止时间，如：16:00

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $outboundCutoffTime = null;  // 当日发货截止时间，如：14:00

    // 物流配送能力
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $coverageAreas = null;  // 覆盖配送区域，如：["华东", "华南"] 或省份列表

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $supportedCarriers = null;  // 支持的物流公司，如：["SF", "JD", "ZTO"]

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $defaultLeadTimeDays = null;  // 默认发货时效（天）

    // 费率配置（平台与仓库的结算）
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $feeConfig = null;  // 费率配置，如：{"storage_per_day": 0.5, "inbound_per_piece": 1.0, "outbound_per_piece": 2.0}

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $settlementCycle = null;  // 结算周期，如：monthly, bi-weekly

    // 服务能力
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $services = null;  // 支持的服务，如：["storage", "packing", "labeling", "returns", "quality_check"]

    // 合作信息
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $contractStartDate = null;  // 合同开始日期

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $contractEndDate = null;  // 合同结束日期

    // API 对接配置
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $apiProvider = null;  // API 提供商/对接类型，如：wms_standard, sf_wms, jd_wms

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $apiConfig = null;  // API 对接配置（加密存储）

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $apiEnabled = false;  // 是否启用 API 对接

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSyncAt = null;  // 最后同步时间

    // 备注
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $internalNotes = null;  // 内部备注

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
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getAreaSquareMeters(): ?string
    {
        return $this->areaSquareMeters;
    }

    public function setAreaSquareMeters(?string $areaSquareMeters): static
    {
        $this->areaSquareMeters = $areaSquareMeters;
        return $this;
    }

    public function getStorageCapacity(): ?int
    {
        return $this->storageCapacity;
    }

    public function setStorageCapacity(?int $storageCapacity): static
    {
        $this->storageCapacity = $storageCapacity;
        return $this;
    }

    public function getSupportedCategories(): ?array
    {
        return $this->supportedCategories;
    }

    public function setSupportedCategories(?array $supportedCategories): static
    {
        $this->supportedCategories = $supportedCategories;
        return $this;
    }

    public function isSupportsColdChain(): bool
    {
        return $this->supportsColdChain;
    }

    public function setSupportsColdChain(bool $supportsColdChain): static
    {
        $this->supportsColdChain = $supportsColdChain;
        return $this;
    }

    public function isSupportsDangerousGoods(): bool
    {
        return $this->supportsDangerousGoods;
    }

    public function setSupportsDangerousGoods(bool $supportsDangerousGoods): static
    {
        $this->supportsDangerousGoods = $supportsDangerousGoods;
        return $this;
    }

    public function getOperatorName(): ?string
    {
        return $this->operatorName;
    }

    public function setOperatorName(?string $operatorName): static
    {
        $this->operatorName = $operatorName;
        return $this;
    }

    public function getOperatorContact(): ?string
    {
        return $this->operatorContact;
    }

    public function setOperatorContact(?string $operatorContact): static
    {
        $this->operatorContact = $operatorContact;
        return $this;
    }

    public function getOperatorPhone(): ?string
    {
        return $this->operatorPhone;
    }

    public function setOperatorPhone(?string $operatorPhone): static
    {
        $this->operatorPhone = $operatorPhone;
        return $this;
    }

    public function getBusinessHours(): ?array
    {
        return $this->businessHours;
    }

    public function setBusinessHours(?array $businessHours): static
    {
        $this->businessHours = $businessHours;
        return $this;
    }

    public function getInboundCutoffTime(): ?string
    {
        return $this->inboundCutoffTime;
    }

    public function setInboundCutoffTime(?string $inboundCutoffTime): static
    {
        $this->inboundCutoffTime = $inboundCutoffTime;
        return $this;
    }

    public function getOutboundCutoffTime(): ?string
    {
        return $this->outboundCutoffTime;
    }

    public function setOutboundCutoffTime(?string $outboundCutoffTime): static
    {
        $this->outboundCutoffTime = $outboundCutoffTime;
        return $this;
    }

    public function getCoverageAreas(): ?array
    {
        return $this->coverageAreas;
    }

    public function setCoverageAreas(?array $coverageAreas): static
    {
        $this->coverageAreas = $coverageAreas;
        return $this;
    }

    public function getSupportedCarriers(): ?array
    {
        return $this->supportedCarriers;
    }

    public function setSupportedCarriers(?array $supportedCarriers): static
    {
        $this->supportedCarriers = $supportedCarriers;
        return $this;
    }

    public function getDefaultLeadTimeDays(): ?int
    {
        return $this->defaultLeadTimeDays;
    }

    public function setDefaultLeadTimeDays(?int $defaultLeadTimeDays): static
    {
        $this->defaultLeadTimeDays = $defaultLeadTimeDays;
        return $this;
    }

    public function getFeeConfig(): ?array
    {
        return $this->feeConfig;
    }

    public function setFeeConfig(?array $feeConfig): static
    {
        $this->feeConfig = $feeConfig;
        return $this;
    }

    public function getFeeConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->feeConfig[$key] ?? $default;
    }

    public function getSettlementCycle(): ?string
    {
        return $this->settlementCycle;
    }

    public function setSettlementCycle(?string $settlementCycle): static
    {
        $this->settlementCycle = $settlementCycle;
        return $this;
    }

    public function getServices(): ?array
    {
        return $this->services;
    }

    public function setServices(?array $services): static
    {
        $this->services = $services;
        return $this;
    }

    public function hasService(string $service): bool
    {
        return $this->services && in_array($service, $this->services, true);
    }

    public function getContractStartDate(): ?\DateTimeImmutable
    {
        return $this->contractStartDate;
    }

    public function setContractStartDate(?\DateTimeImmutable $contractStartDate): static
    {
        $this->contractStartDate = $contractStartDate;
        return $this;
    }

    public function getContractEndDate(): ?\DateTimeImmutable
    {
        return $this->contractEndDate;
    }

    public function setContractEndDate(?\DateTimeImmutable $contractEndDate): static
    {
        $this->contractEndDate = $contractEndDate;
        return $this;
    }

    public function getApiProvider(): ?string
    {
        return $this->apiProvider;
    }

    public function setApiProvider(?string $apiProvider): static
    {
        $this->apiProvider = $apiProvider;
        return $this;
    }

    public function getApiConfig(): ?array
    {
        return $this->apiConfig;
    }

    public function setApiConfig(?array $apiConfig): static
    {
        $this->apiConfig = $apiConfig;
        return $this;
    }

    public function getApiConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->apiConfig[$key] ?? $default;
    }

    public function isApiEnabled(): bool
    {
        return $this->apiEnabled;
    }

    public function setApiEnabled(bool $apiEnabled): static
    {
        $this->apiEnabled = $apiEnabled;
        return $this;
    }

    public function getLastSyncAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncAt;
    }

    public function setLastSyncAt(?\DateTimeImmutable $lastSyncAt): static
    {
        $this->lastSyncAt = $lastSyncAt;
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
        $this->updatedAt = new \DateTimeImmutable();
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

    public function isContractValid(): bool
    {
        $now = new \DateTimeImmutable();

        if ($this->contractStartDate && $this->contractStartDate > $now) {
            return false;
        }

        if ($this->contractEndDate && $this->contractEndDate < $now) {
            return false;
        }

        return true;
    }

    public function isDomestic(): bool
    {
        return $this->countryCode === 'CN';
    }

    public function supportsCarrier(string $carrier): bool
    {
        return $this->supportedCarriers && in_array($carrier, $this->supportedCarriers, true);
    }

    /**
     * 是否为平台仓库（送仓模式）
     */
    public function isPlatformWarehouse(): bool
    {
        return $this->category === self::CATEGORY_PLATFORM;
    }

    /**
     * 是否为商家自有仓库（不送仓模式）
     */
    public function isMerchantWarehouse(): bool
    {
        return $this->category === self::CATEGORY_MERCHANT;
    }

    /**
     * 创建商家自有仓库的工厂方法
     */
    public static function createMerchantWarehouse(Merchant $merchant, string $code, string $name): static
    {
        $warehouse = new static();
        $warehouse->setCategory(self::CATEGORY_MERCHANT);
        $warehouse->setMerchant($merchant);
        $warehouse->setCode($code);
        $warehouse->setName($name);
        $warehouse->setType(self::TYPE_SELF);  // 商家自有仓默认为自营类型
        return $warehouse;
    }
}