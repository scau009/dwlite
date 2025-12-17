<?php

namespace App\Entity;

use App\Repository\InventoryListingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 库存上架配置 - 商家侧
 *
 * 商家将库存上架到指定渠道的配置，包含定价和库存分配策略
 */
#[ORM\Entity(repositoryClass: InventoryListingRepository::class)]
#[ORM\Table(name: 'inventory_listings')]
#[ORM\UniqueConstraint(name: 'uniq_inventory_channel', columns: ['merchant_inventory_id', 'merchant_sales_channel_id'])]
#[ORM\Index(name: 'idx_listing_inventory', columns: ['merchant_inventory_id'])]
#[ORM\Index(name: 'idx_listing_channel', columns: ['merchant_sales_channel_id'])]
#[ORM\Index(name: 'idx_listing_status', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class InventoryListing
{
    // 库存分配模式
    public const MODE_SHARED = 'shared';       // 半托管：共享库存，多渠道共用
    public const MODE_DEDICATED = 'dedicated'; // 全托管：独占库存，预先锁定

    // 上架状态
    public const STATUS_DRAFT = 'draft';       // 草稿
    public const STATUS_ACTIVE = 'active';     // 已上架
    public const STATUS_PAUSED = 'paused';     // 已暂停
    public const STATUS_SOLD_OUT = 'sold_out'; // 已售罄

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: MerchantInventory::class)]
    #[ORM\JoinColumn(name: 'merchant_inventory_id', nullable: false, onDelete: 'CASCADE')]
    private MerchantInventory $merchantInventory;

    #[ORM\ManyToOne(targetEntity: MerchantSalesChannel::class)]
    #[ORM\JoinColumn(name: 'merchant_sales_channel_id', nullable: false, onDelete: 'CASCADE')]
    private MerchantSalesChannel $merchantSalesChannel;

    // 库存分配
    #[ORM\Column(type: 'string', length: 20)]
    private string $allocationMode = self::MODE_SHARED;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $allocatedQuantity = null;  // 独占模式下分配的数量

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $soldQuantity = 0;  // 该渠道已售数量

    // 定价
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $price;  // 售价

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $compareAtPrice = null;  // 原价/划线价

    // 状态
    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_DRAFT;

    // 备注
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $remark = null;

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

    public function getMerchantInventory(): MerchantInventory
    {
        return $this->merchantInventory;
    }

    public function setMerchantInventory(MerchantInventory $merchantInventory): static
    {
        $this->merchantInventory = $merchantInventory;
        return $this;
    }

    public function getMerchantSalesChannel(): MerchantSalesChannel
    {
        return $this->merchantSalesChannel;
    }

    public function setMerchantSalesChannel(MerchantSalesChannel $merchantSalesChannel): static
    {
        $this->merchantSalesChannel = $merchantSalesChannel;
        return $this;
    }

    public function getAllocationMode(): string
    {
        return $this->allocationMode;
    }

    public function setAllocationMode(string $allocationMode): static
    {
        $this->allocationMode = $allocationMode;
        return $this;
    }

    public function getAllocatedQuantity(): ?int
    {
        return $this->allocatedQuantity;
    }

    public function setAllocatedQuantity(?int $allocatedQuantity): static
    {
        $this->allocatedQuantity = $allocatedQuantity;
        return $this;
    }

    public function getSoldQuantity(): int
    {
        return $this->soldQuantity;
    }

    public function setSoldQuantity(int $soldQuantity): static
    {
        $this->soldQuantity = $soldQuantity;
        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getCompareAtPrice(): ?string
    {
        return $this->compareAtPrice;
    }

    public function setCompareAtPrice(?string $compareAtPrice): static
    {
        $this->compareAtPrice = $compareAtPrice;
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

    public function isShared(): bool
    {
        return $this->allocationMode === self::MODE_SHARED;
    }

    public function isDedicated(): bool
    {
        return $this->allocationMode === self::MODE_DEDICATED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    public function isSoldOut(): bool
    {
        return $this->status === self::STATUS_SOLD_OUT;
    }

    /**
     * 获取可售数量
     */
    public function getAvailableQuantity(): int
    {
        if ($this->isDedicated()) {
            // 独占模式：可售 = 分配数量 - 已售数量
            return max(0, ($this->allocatedQuantity ?? 0) - $this->soldQuantity);
        }

        // 共享模式：可售 = 库存可共享数量
        return $this->merchantInventory->getShareableQuantity();
    }

    /**
     * 是否有可售库存
     */
    public function hasAvailableStock(): bool
    {
        return $this->getAvailableQuantity() > 0;
    }

    /**
     * 激活上架
     */
    public function activate(): void
    {
        if (!$this->hasAvailableStock()) {
            throw new \LogicException('Cannot activate listing without available stock');
        }
        $this->status = self::STATUS_ACTIVE;
    }

    /**
     * 暂停上架
     */
    public function pause(): void
    {
        $this->status = self::STATUS_PAUSED;
    }

    /**
     * 记录销售
     */
    public function recordSale(int $quantity): void
    {
        $this->soldQuantity += $quantity;

        // 检查是否售罄
        if ($this->isDedicated() && $this->getAvailableQuantity() <= 0) {
            $this->status = self::STATUS_SOLD_OUT;
        }
    }

    /**
     * 设置为独占模式并分配库存
     */
    public function setDedicatedAllocation(int $quantity): void
    {
        $inventory = $this->merchantInventory;

        // 如果之前是独占模式，先释放之前的分配
        if ($this->isDedicated() && $this->allocatedQuantity !== null) {
            $inventory->deallocate($this->allocatedQuantity);
        }

        // 分配新的库存
        $inventory->allocate($quantity);

        $this->allocationMode = self::MODE_DEDICATED;
        $this->allocatedQuantity = $quantity;
    }

    /**
     * 切换为共享模式
     */
    public function setSharedAllocation(): void
    {
        // 如果之前是独占模式，释放分配的库存
        if ($this->isDedicated() && $this->allocatedQuantity !== null) {
            $this->merchantInventory->deallocate($this->allocatedQuantity);
        }

        $this->allocationMode = self::MODE_SHARED;
        $this->allocatedQuantity = null;
    }

    /**
     * 调整独占分配数量
     */
    public function adjustAllocatedQuantity(int $newQuantity): void
    {
        if (!$this->isDedicated()) {
            throw new \LogicException('Can only adjust allocation in dedicated mode');
        }

        $currentAllocation = $this->allocatedQuantity ?? 0;
        $difference = $newQuantity - $currentAllocation;

        if ($difference > 0) {
            // 增加分配
            $this->merchantInventory->allocate($difference);
        } elseif ($difference < 0) {
            // 减少分配
            $this->merchantInventory->deallocate(-$difference);
        }

        $this->allocatedQuantity = $newQuantity;
    }
}