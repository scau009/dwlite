<?php

namespace App\Entity;

use App\Repository\FulfillmentItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 履约单明细 - 履约单中的商品明细，关联到具体的库存来源
 */
#[ORM\Entity(repositoryClass: FulfillmentItemRepository::class)]
#[ORM\Table(name: 'fulfillment_items')]
#[ORM\Index(name: 'idx_fulfillment_item_fulfillment', columns: ['fulfillment_id'])]
#[ORM\Index(name: 'idx_fulfillment_item_order_item', columns: ['order_item_id'])]
#[ORM\Index(name: 'idx_fulfillment_item_inventory', columns: ['merchant_inventory_id'])]
#[ORM\HasLifecycleCallbacks]
class FulfillmentItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Fulfillment::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'fulfillment_id', nullable: false, onDelete: 'CASCADE')]
    private Fulfillment $fulfillment;

    #[ORM\ManyToOne(targetEntity: OrderItem::class)]
    #[ORM\JoinColumn(name: 'order_item_id', nullable: false)]
    private OrderItem $orderItem;

    // 库存来源
    #[ORM\ManyToOne(targetEntity: MerchantInventory::class)]
    #[ORM\JoinColumn(name: 'merchant_inventory_id', nullable: false)]
    private MerchantInventory $merchantInventory;

    // 上架配置（用于获取价格和结算信息）
    #[ORM\ManyToOne(targetEntity: InventoryListing::class)]
    #[ORM\JoinColumn(name: 'inventory_listing_id', nullable: true)]
    private ?InventoryListing $inventoryListing = null;

    // 数量
    #[ORM\Column(type: 'integer')]
    private int $quantity;

    // 结算信息（从 InventoryListing 快照）
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $settlementPrice = null;  // 结算价格（商家获得的金额）

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $commissionRate = null;  // 佣金比例

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $commissionAmount = null;  // 佣金金额

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

    public function getFulfillment(): Fulfillment
    {
        return $this->fulfillment;
    }

    public function setFulfillment(Fulfillment $fulfillment): static
    {
        $this->fulfillment = $fulfillment;
        return $this;
    }

    public function getOrderItem(): OrderItem
    {
        return $this->orderItem;
    }

    public function setOrderItem(OrderItem $orderItem): static
    {
        $this->orderItem = $orderItem;
        return $this;
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

    public function getInventoryListing(): ?InventoryListing
    {
        return $this->inventoryListing;
    }

    public function setInventoryListing(?InventoryListing $inventoryListing): static
    {
        $this->inventoryListing = $inventoryListing;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getSettlementPrice(): ?string
    {
        return $this->settlementPrice;
    }

    public function setSettlementPrice(?string $settlementPrice): static
    {
        $this->settlementPrice = $settlementPrice;
        return $this;
    }

    public function getCommissionRate(): ?string
    {
        return $this->commissionRate;
    }

    public function setCommissionRate(?string $commissionRate): static
    {
        $this->commissionRate = $commissionRate;
        return $this;
    }

    public function getCommissionAmount(): ?string
    {
        return $this->commissionAmount;
    }

    public function setCommissionAmount(?string $commissionAmount): static
    {
        $this->commissionAmount = $commissionAmount;
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
     * 获取关联的商家
     */
    public function getMerchant(): Merchant
    {
        return $this->merchantInventory->getMerchant();
    }

    /**
     * 获取关联的仓库
     */
    public function getWarehouse(): Warehouse
    {
        return $this->merchantInventory->getWarehouse();
    }

    /**
     * 获取关联的 SKU
     */
    public function getProductSku(): ProductSku
    {
        return $this->merchantInventory->getProductSku();
    }

    /**
     * 计算结算金额
     */
    public function calculateSettlement(): void
    {
        if ($this->inventoryListing === null || $this->settlementPrice === null) {
            return;
        }

        // 结算总额 = 结算单价 * 数量
        $totalSettlement = bcmul($this->settlementPrice, (string) $this->quantity, 2);

        // 如果有佣金比例，计算佣金
        if ($this->commissionRate !== null) {
            $this->commissionAmount = bcmul(
                $totalSettlement,
                bcdiv($this->commissionRate, '100', 4),
                2
            );
        }
    }

    /**
     * 获取结算总金额
     */
    public function getSettlementTotal(): ?string
    {
        if ($this->settlementPrice === null) {
            return null;
        }

        return bcmul($this->settlementPrice, (string) $this->quantity, 2);
    }

    /**
     * 从 InventoryListing 快照结算信息
     */
    public function snapshotFromListing(InventoryListing $listing): void
    {
        $this->inventoryListing = $listing;
        $this->settlementPrice = $listing->getListPrice();
        // 佣金比例可以从其他配置获取，这里暂不设置
    }
}