<?php

namespace App\Entity;

use App\Repository\ChannelProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 渠道商品 - 平台侧
 *
 * 平台对外销售渠道的商品，聚合多个商家的库存，对外隐藏商家信息
 */
#[ORM\Entity(repositoryClass: ChannelProductRepository::class)]
#[ORM\Table(name: 'channel_products')]
#[ORM\UniqueConstraint(name: 'uniq_channel_sku', columns: ['sales_channel_id', 'product_sku_id'])]
#[ORM\Index(name: 'idx_cp_channel', columns: ['sales_channel_id'])]
#[ORM\Index(name: 'idx_cp_sku', columns: ['product_sku_id'])]
#[ORM\Index(name: 'idx_cp_status', columns: ['status'])]
#[ORM\Index(name: 'idx_cp_external', columns: ['external_id'])]
#[ORM\HasLifecycleCallbacks]
class ChannelProduct
{
    // 库存聚合策略
    public const STOCK_MODE_AGGREGATE = 'aggregate';  // 聚合：所有来源库存相加
    public const STOCK_MODE_LOWEST = 'lowest';        // 最低：取最低来源的库存
    public const STOCK_MODE_FIXED = 'fixed';          // 固定：使用固定值

    // 同步状态
    public const SYNC_STATUS_PENDING = 'pending';     // 待同步
    public const SYNC_STATUS_SYNCING = 'syncing';     // 同步中
    public const SYNC_STATUS_SYNCED = 'synced';       // 已同步
    public const SYNC_STATUS_FAILED = 'failed';       // 同步失败

    // 上架状态
    public const STATUS_DRAFT = 'draft';              // 草稿
    public const STATUS_PENDING = 'pending';          // 待审核
    public const STATUS_ACTIVE = 'active';            // 已上架
    public const STATUS_PAUSED = 'paused';            // 已暂停
    public const STATUS_REJECTED = 'rejected';        // 已拒绝

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: SalesChannel::class)]
    #[ORM\JoinColumn(name: 'sales_channel_id', nullable: false)]
    private SalesChannel $salesChannel;

    #[ORM\ManyToOne(targetEntity: ProductSku::class)]
    #[ORM\JoinColumn(name: 'product_sku_id', nullable: false)]
    private ProductSku $productSku;

    // 平台定价（可能与商家价格不同）
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $platformPrice;  // 平台统一售价

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $platformCompareAtPrice = null;  // 平台划线价

    // 库存策略
    #[ORM\Column(type: 'string', length: 20)]
    private string $stockMode = self::STOCK_MODE_AGGREGATE;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $stockQuantity = 0;  // 计算后的对外库存

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $safetyBuffer = 0;  // 安全缓冲（防超卖）

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $fixedStock = null;  // 固定库存值（stockMode=fixed 时使用）

    // 外部渠道对接
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $externalId = null;  // 外部平台商品 ID

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $externalUrl = null;  // 外部平台商品链接

    #[ORM\Column(type: 'string', length: 20)]
    private string $syncStatus = self::SYNC_STATUS_PENDING;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $syncError = null;  // 最后一次同步错误信息

    // 状态
    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_DRAFT;

    // 统计
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $totalSoldQuantity = 0;  // 总销量

    // 关联来源
    #[ORM\OneToMany(targetEntity: ChannelProductSource::class, mappedBy: 'channelProduct', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['priority' => 'ASC'])]
    private Collection $sources;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->sources = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): string
    {
        return $this->id;
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

    public function getProductSku(): ProductSku
    {
        return $this->productSku;
    }

    public function setProductSku(ProductSku $productSku): static
    {
        $this->productSku = $productSku;
        return $this;
    }

    public function getPlatformPrice(): string
    {
        return $this->platformPrice;
    }

    public function setPlatformPrice(string $platformPrice): static
    {
        $this->platformPrice = $platformPrice;
        return $this;
    }

    public function getPlatformCompareAtPrice(): ?string
    {
        return $this->platformCompareAtPrice;
    }

    public function setPlatformCompareAtPrice(?string $platformCompareAtPrice): static
    {
        $this->platformCompareAtPrice = $platformCompareAtPrice;
        return $this;
    }

    public function getStockMode(): string
    {
        return $this->stockMode;
    }

    public function setStockMode(string $stockMode): static
    {
        $this->stockMode = $stockMode;
        return $this;
    }

    public function getStockQuantity(): int
    {
        return $this->stockQuantity;
    }

    public function setStockQuantity(int $stockQuantity): static
    {
        $this->stockQuantity = $stockQuantity;
        return $this;
    }

    public function getSafetyBuffer(): int
    {
        return $this->safetyBuffer;
    }

    public function setSafetyBuffer(int $safetyBuffer): static
    {
        $this->safetyBuffer = $safetyBuffer;
        return $this;
    }

    public function getFixedStock(): ?int
    {
        return $this->fixedStock;
    }

    public function setFixedStock(?int $fixedStock): static
    {
        $this->fixedStock = $fixedStock;
        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): static
    {
        $this->externalId = $externalId;
        return $this;
    }

    public function getExternalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function setExternalUrl(?string $externalUrl): static
    {
        $this->externalUrl = $externalUrl;
        return $this;
    }

    public function getSyncStatus(): string
    {
        return $this->syncStatus;
    }

    public function setSyncStatus(string $syncStatus): static
    {
        $this->syncStatus = $syncStatus;
        return $this;
    }

    public function getLastSyncedAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function setLastSyncedAt(?\DateTimeImmutable $lastSyncedAt): static
    {
        $this->lastSyncedAt = $lastSyncedAt;
        return $this;
    }

    public function getSyncError(): ?string
    {
        return $this->syncError;
    }

    public function setSyncError(?string $syncError): static
    {
        $this->syncError = $syncError;
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

    public function getTotalSoldQuantity(): int
    {
        return $this->totalSoldQuantity;
    }

    public function setTotalSoldQuantity(int $totalSoldQuantity): static
    {
        $this->totalSoldQuantity = $totalSoldQuantity;
        return $this;
    }

    /**
     * @return Collection<int, ChannelProductSource>
     */
    public function getSources(): Collection
    {
        return $this->sources;
    }

    public function addSource(ChannelProductSource $source): static
    {
        if (!$this->sources->contains($source)) {
            $this->sources->add($source);
            $source->setChannelProduct($this);
        }
        return $this;
    }

    public function removeSource(ChannelProductSource $source): static
    {
        if ($this->sources->removeElement($source)) {
            if ($source->getChannelProduct() === $this) {
                $source->setChannelProduct($this);
            }
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

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isSynced(): bool
    {
        return $this->syncStatus === self::SYNC_STATUS_SYNCED;
    }

    public function needsSync(): bool
    {
        return $this->syncStatus === self::SYNC_STATUS_PENDING;
    }

    /**
     * 获取活跃的供货来源
     *
     * @return Collection<int, ChannelProductSource>
     */
    public function getActiveSources(): Collection
    {
        return $this->sources->filter(fn(ChannelProductSource $source) => $source->isActive());
    }

    /**
     * 重新计算库存
     */
    public function recalculateStock(): void
    {
        $activeSources = $this->getActiveSources();

        if ($activeSources->isEmpty()) {
            $this->stockQuantity = 0;
            return;
        }

        $stock = match ($this->stockMode) {
            self::STOCK_MODE_AGGREGATE => $this->calculateAggregateStock($activeSources),
            self::STOCK_MODE_LOWEST => $this->calculateLowestStock($activeSources),
            self::STOCK_MODE_FIXED => $this->fixedStock ?? 0,
            default => 0,
        };

        // 减去安全缓冲
        $this->stockQuantity = max(0, $stock - $this->safetyBuffer);
    }

    /**
     * 聚合库存计算
     */
    private function calculateAggregateStock(Collection $sources): int
    {
        $total = 0;
        foreach ($sources as $source) {
            $total += $source->getInventoryListing()->getAvailableQuantity();
        }
        return $total;
    }

    /**
     * 最低库存计算
     */
    private function calculateLowestStock(Collection $sources): int
    {
        $lowest = PHP_INT_MAX;
        foreach ($sources as $source) {
            $qty = $source->getInventoryListing()->getAvailableQuantity();
            if ($qty < $lowest) {
                $lowest = $qty;
            }
        }
        return $lowest === PHP_INT_MAX ? 0 : $lowest;
    }

    /**
     * 标记需要同步
     */
    public function markNeedsSync(): void
    {
        $this->syncStatus = self::SYNC_STATUS_PENDING;
    }

    /**
     * 标记同步成功
     */
    public function markSynced(): void
    {
        $this->syncStatus = self::SYNC_STATUS_SYNCED;
        $this->lastSyncedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->syncError = null;
    }

    /**
     * 标记同步失败
     */
    public function markSyncFailed(string $error): void
    {
        $this->syncStatus = self::SYNC_STATUS_FAILED;
        $this->syncError = $error;
    }

    /**
     * 记录销售
     */
    public function recordSale(int $quantity): void
    {
        $this->totalSoldQuantity += $quantity;
    }

    /**
     * 激活上架
     */
    public function activate(): void
    {
        $this->status = self::STATUS_ACTIVE;
        $this->markNeedsSync();
    }

    /**
     * 暂停上架
     */
    public function pause(): void
    {
        $this->status = self::STATUS_PAUSED;
        $this->markNeedsSync();
    }
}