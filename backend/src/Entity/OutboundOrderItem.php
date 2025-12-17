<?php

namespace App\Entity;

use App\Repository\OutboundOrderItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 出库单明细 - 出库单中的商品明细
 */
#[ORM\Entity(repositoryClass: OutboundOrderItemRepository::class)]
#[ORM\Table(name: 'outbound_order_items')]
#[ORM\Index(name: 'idx_outbound_item_outbound', columns: ['outbound_order_id'])]
#[ORM\Index(name: 'idx_outbound_item_sku', columns: ['product_sku_id'])]
#[ORM\Index(name: 'idx_outbound_item_inventory', columns: ['merchant_inventory_id'])]
#[ORM\HasLifecycleCallbacks]
class OutboundOrderItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: OutboundOrder::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'outbound_order_id', nullable: false, onDelete: 'CASCADE')]
    private OutboundOrder $outboundOrder;

    #[ORM\ManyToOne(targetEntity: FulfillmentItem::class)]
    #[ORM\JoinColumn(name: 'fulfillment_item_id', nullable: false)]
    private FulfillmentItem $fulfillmentItem;

    #[ORM\ManyToOne(targetEntity: ProductSku::class)]
    #[ORM\JoinColumn(name: 'product_sku_id', nullable: false)]
    private ProductSku $productSku;

    #[ORM\ManyToOne(targetEntity: MerchantInventory::class)]
    #[ORM\JoinColumn(name: 'merchant_inventory_id', nullable: false)]
    private MerchantInventory $merchantInventory;

    // 数量
    #[ORM\Column(type: 'integer')]
    private int $quantity;  // 应出库数量

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $pickedQuantity = 0;  // 已拣货数量

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $shippedQuantity = 0;  // 实际发货数量

    // 库位信息（从库存获取或 WMS 返回）
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $locationCode = null;  // 库位编码

    // 批次/序列号（可选）
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $batchNo = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $serialNumbers = null;

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

    public function getOutboundOrder(): OutboundOrder
    {
        return $this->outboundOrder;
    }

    public function setOutboundOrder(OutboundOrder $outboundOrder): static
    {
        $this->outboundOrder = $outboundOrder;
        return $this;
    }

    public function getFulfillmentItem(): FulfillmentItem
    {
        return $this->fulfillmentItem;
    }

    public function setFulfillmentItem(FulfillmentItem $fulfillmentItem): static
    {
        $this->fulfillmentItem = $fulfillmentItem;
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

    public function getMerchantInventory(): MerchantInventory
    {
        return $this->merchantInventory;
    }

    public function setMerchantInventory(MerchantInventory $merchantInventory): static
    {
        $this->merchantInventory = $merchantInventory;
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

    public function getPickedQuantity(): int
    {
        return $this->pickedQuantity;
    }

    public function setPickedQuantity(int $pickedQuantity): static
    {
        $this->pickedQuantity = $pickedQuantity;
        return $this;
    }

    public function getShippedQuantity(): int
    {
        return $this->shippedQuantity;
    }

    public function setShippedQuantity(int $shippedQuantity): static
    {
        $this->shippedQuantity = $shippedQuantity;
        return $this;
    }

    public function getLocationCode(): ?string
    {
        return $this->locationCode;
    }

    public function setLocationCode(?string $locationCode): static
    {
        $this->locationCode = $locationCode;
        return $this;
    }

    public function getBatchNo(): ?string
    {
        return $this->batchNo;
    }

    public function setBatchNo(?string $batchNo): static
    {
        $this->batchNo = $batchNo;
        return $this;
    }

    public function getSerialNumbers(): ?array
    {
        return $this->serialNumbers;
    }

    public function setSerialNumbers(?array $serialNumbers): static
    {
        $this->serialNumbers = $serialNumbers;
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
     * 是否已全部拣货
     */
    public function isFullyPicked(): bool
    {
        return $this->pickedQuantity >= $this->quantity;
    }

    /**
     * 是否已全部发货
     */
    public function isFullyShipped(): bool
    {
        return $this->shippedQuantity >= $this->quantity;
    }

    /**
     * 获取待拣货数量
     */
    public function getPendingPickQuantity(): int
    {
        return max(0, $this->quantity - $this->pickedQuantity);
    }

    /**
     * 记录拣货
     */
    public function recordPicked(int $quantity): void
    {
        $this->pickedQuantity += $quantity;
    }

    /**
     * 记录发货
     */
    public function recordShipped(int $quantity): void
    {
        $this->shippedQuantity += $quantity;
    }

    /**
     * 从履约单明细创建
     */
    public static function createFromFulfillmentItem(FulfillmentItem $fulfillmentItem): static
    {
        $item = new static();
        $item->setFulfillmentItem($fulfillmentItem);
        $item->setProductSku($fulfillmentItem->getProductSku());
        $item->setMerchantInventory($fulfillmentItem->getMerchantInventory());
        $item->setQuantity($fulfillmentItem->getQuantity());
        return $item;
    }
}