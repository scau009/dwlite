<?php

namespace App\Entity;

use App\Repository\ChannelProductSourceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 渠道商品来源 - 关联平台商品与商家上架配置
 *
 * 一个渠道商品可以有多个供货来源（多商家供货）
 */
#[ORM\Entity(repositoryClass: ChannelProductSourceRepository::class)]
#[ORM\Table(name: 'channel_product_sources')]
#[ORM\UniqueConstraint(name: 'uniq_product_listing', columns: ['channel_product_id', 'inventory_listing_id'])]
#[ORM\Index(name: 'idx_cps_product', columns: ['channel_product_id'])]
#[ORM\Index(name: 'idx_cps_listing', columns: ['inventory_listing_id'])]
#[ORM\HasLifecycleCallbacks]
class ChannelProductSource
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: ChannelProduct::class, inversedBy: 'sources')]
    #[ORM\JoinColumn(name: 'channel_product_id', nullable: false, onDelete: 'CASCADE')]
    private ChannelProduct $channelProduct;

    #[ORM\ManyToOne(targetEntity: InventoryListing::class)]
    #[ORM\JoinColumn(name: 'inventory_listing_id', nullable: false, onDelete: 'CASCADE')]
    private InventoryListing $inventoryListing;

    // 优先级（用于决定发货顺序，数值越小优先级越高）
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $priority = 0;

    // 是否启用
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    // 该来源的已售数量
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $soldQuantity = 0;

    // 备注
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
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

    public function getChannelProduct(): ChannelProduct
    {
        return $this->channelProduct;
    }

    public function setChannelProduct(ChannelProduct $channelProduct): static
    {
        $this->channelProduct = $channelProduct;
        return $this;
    }

    public function getInventoryListing(): InventoryListing
    {
        return $this->inventoryListing;
    }

    public function setInventoryListing(InventoryListing $inventoryListing): static
    {
        $this->inventoryListing = $inventoryListing;
        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
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

    /**
     * 启用该来源
     */
    public function activate(): void
    {
        $this->isActive = true;
    }

    /**
     * 禁用该来源
     */
    public function deactivate(): void
    {
        $this->isActive = false;
    }

    /**
     * 记录销售
     */
    public function recordSale(int $quantity): void
    {
        $this->soldQuantity += $quantity;
        // 同步更新商家侧的销售记录
        $this->inventoryListing->recordSale($quantity);
    }

    /**
     * 获取可用库存（来自关联的商家上架配置）
     */
    public function getAvailableQuantity(): int
    {
        if (!$this->isActive) {
            return 0;
        }
        return $this->inventoryListing->getAvailableQuantity();
    }

    /**
     * 获取商家
     */
    public function getMerchant(): Merchant
    {
        return $this->inventoryListing->getMerchantInventory()->getMerchant();
    }

    /**
     * 获取商家报价
     */
    public function getMerchantPrice(): string
    {
        return $this->inventoryListing->getPrice();
    }
}