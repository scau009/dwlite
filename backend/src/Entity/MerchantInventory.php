<?php

namespace App\Entity;

use App\Repository\MerchantInventoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 商户库存 - 商户在某仓库的 SKU 库存
 */
#[ORM\Entity(repositoryClass: MerchantInventoryRepository::class)]
#[ORM\Table(name: 'merchant_inventories')]
#[ORM\UniqueConstraint(name: 'uniq_merchant_warehouse_sku', columns: ['merchant_id', 'warehouse_id', 'product_sku_id'])]
#[ORM\Index(name: 'idx_inventory_merchant', columns: ['merchant_id'])]
#[ORM\Index(name: 'idx_inventory_warehouse', columns: ['warehouse_id'])]
#[ORM\Index(name: 'idx_inventory_sku', columns: ['product_sku_id'])]
#[ORM\HasLifecycleCallbacks]
class MerchantInventory
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Merchant::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Merchant $merchant;

    #[ORM\ManyToOne(targetEntity: Warehouse::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Warehouse $warehouse;

    #[ORM\ManyToOne(targetEntity: ProductSku::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ProductSku $productSku;

    // 库存数量
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $quantityInTransit = 0;  // 在途数量（已发货未入库）

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $quantityAvailable = 0;  // 可用库存（可以被销售）

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $quantityReserved = 0;  // 锁定库存（已被订单占用，待出库）

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $quantityDamaged = 0;  // 损坏库存（不可销售）

    // 成本信息（加权平均成本）
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $averageCost = null;  // 平均成本单价

    // 安全库存
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $safetyStock = null;  // 安全库存量（低于此值预警）

    // 统计信息
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastInboundAt = null;  // 最后入库时间

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastOutboundAt = null;  // 最后出库时间

    // 关联库存流水
    #[ORM\OneToMany(targetEntity: InventoryTransaction::class, mappedBy: 'merchantInventory')]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $transactions;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->transactions = new ArrayCollection();
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

    public function getWarehouse(): Warehouse
    {
        return $this->warehouse;
    }

    public function setWarehouse(Warehouse $warehouse): static
    {
        $this->warehouse = $warehouse;
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

    public function getQuantityInTransit(): int
    {
        return $this->quantityInTransit;
    }

    public function setQuantityInTransit(int $quantityInTransit): static
    {
        $this->quantityInTransit = $quantityInTransit;
        return $this;
    }

    public function getQuantityAvailable(): int
    {
        return $this->quantityAvailable;
    }

    public function setQuantityAvailable(int $quantityAvailable): static
    {
        $this->quantityAvailable = $quantityAvailable;
        return $this;
    }

    public function getQuantityReserved(): int
    {
        return $this->quantityReserved;
    }

    public function setQuantityReserved(int $quantityReserved): static
    {
        $this->quantityReserved = $quantityReserved;
        return $this;
    }

    public function getQuantityDamaged(): int
    {
        return $this->quantityDamaged;
    }

    public function setQuantityDamaged(int $quantityDamaged): static
    {
        $this->quantityDamaged = $quantityDamaged;
        return $this;
    }

    public function getAverageCost(): ?string
    {
        return $this->averageCost;
    }

    public function setAverageCost(?string $averageCost): static
    {
        $this->averageCost = $averageCost;
        return $this;
    }

    public function getSafetyStock(): ?int
    {
        return $this->safetyStock;
    }

    public function setSafetyStock(?int $safetyStock): static
    {
        $this->safetyStock = $safetyStock;
        return $this;
    }

    public function getLastInboundAt(): ?\DateTimeImmutable
    {
        return $this->lastInboundAt;
    }

    public function setLastInboundAt(?\DateTimeImmutable $lastInboundAt): static
    {
        $this->lastInboundAt = $lastInboundAt;
        return $this;
    }

    public function getLastOutboundAt(): ?\DateTimeImmutable
    {
        return $this->lastOutboundAt;
    }

    public function setLastOutboundAt(?\DateTimeImmutable $lastOutboundAt): static
    {
        $this->lastOutboundAt = $lastOutboundAt;
        return $this;
    }

    /**
     * @return Collection<int, InventoryTransaction>
     */
    public function getTransactions(): Collection
    {
        return $this->transactions;
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
     * 获取总库存（在仓 + 锁定，不含在途和损坏）
     */
    public function getTotalOnHand(): int
    {
        return $this->quantityAvailable + $this->quantityReserved;
    }

    /**
     * 获取全部库存（含在途）
     */
    public function getTotalQuantity(): int
    {
        return $this->quantityInTransit + $this->quantityAvailable + $this->quantityReserved + $this->quantityDamaged;
    }

    /**
     * 是否低于安全库存
     */
    public function isBelowSafetyStock(): bool
    {
        if ($this->safetyStock === null) {
            return false;
        }
        return $this->quantityAvailable < $this->safetyStock;
    }

    /**
     * 是否有可用库存
     */
    public function hasAvailableStock(): bool
    {
        return $this->quantityAvailable > 0;
    }

    /**
     * 增加在途库存（发货时调用）
     */
    public function addInTransit(int $quantity): void
    {
        $this->quantityInTransit += $quantity;
    }

    /**
     * 在途转可用（入库完成时调用）
     */
    public function confirmInbound(int $quantity, int $damagedQuantity = 0): void
    {
        $this->quantityInTransit -= ($quantity + $damagedQuantity);
        $this->quantityAvailable += $quantity;
        $this->quantityDamaged += $damagedQuantity;
        $this->lastInboundAt = new \DateTimeImmutable();

        // 确保在途数量不为负
        if ($this->quantityInTransit < 0) {
            $this->quantityInTransit = 0;
        }
    }

    /**
     * 锁定库存（订单占用）
     */
    public function reserve(int $quantity): void
    {
        if ($quantity > $this->quantityAvailable) {
            throw new \LogicException('Insufficient available inventory');
        }
        $this->quantityAvailable -= $quantity;
        $this->quantityReserved += $quantity;
    }

    /**
     * 释放锁定库存（订单取消）
     */
    public function release(int $quantity): void
    {
        if ($quantity > $this->quantityReserved) {
            throw new \LogicException('Cannot release more than reserved');
        }
        $this->quantityReserved -= $quantity;
        $this->quantityAvailable += $quantity;
    }

    /**
     * 确认出库（发货扣减）
     */
    public function confirmOutbound(int $quantity): void
    {
        if ($quantity > $this->quantityReserved) {
            throw new \LogicException('Cannot ship more than reserved');
        }
        $this->quantityReserved -= $quantity;
        $this->lastOutboundAt = new \DateTimeImmutable();
    }

    /**
     * 更新加权平均成本
     */
    public function updateAverageCost(int $newQuantity, string $newUnitCost): void
    {
        $currentTotal = $this->quantityAvailable + $this->quantityReserved;

        if ($currentTotal === 0 || $this->averageCost === null) {
            $this->averageCost = $newUnitCost;
            return;
        }

        // 加权平均：(现有库存 * 现有成本 + 新入库 * 新成本) / 总库存
        $currentValue = bcmul((string) $currentTotal, $this->averageCost, 4);
        $newValue = bcmul((string) $newQuantity, $newUnitCost, 4);
        $totalQuantity = $currentTotal + $newQuantity;

        if ($totalQuantity > 0) {
            $this->averageCost = bcdiv(bcadd($currentValue, $newValue, 4), (string) $totalQuantity, 2);
        }
    }
}
