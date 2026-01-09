<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SettlementItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * SettlementItem - 结算明细，记录每个履约单项的结算信息.
 */
#[ORM\Entity(repositoryClass: SettlementItemRepository::class)]
#[ORM\Table(name: 'settlement_items')]
#[ORM\Index(name: 'idx_si_settlement', columns: ['settlement_id'])]
#[ORM\Index(name: 'idx_si_fulfillment_item', columns: ['fulfillment_item_id'])]
class SettlementItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Settlement::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'settlement_id', nullable: false, onDelete: 'CASCADE')]
    private Settlement $settlement;

    #[ORM\ManyToOne(targetEntity: FulfillmentItem::class)]
    #[ORM\JoinColumn(name: 'fulfillment_item_id', nullable: false)]
    private FulfillmentItem $fulfillmentItem;

    // SKU 快照（用于报表）
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $skuCode = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $productName = null;

    // 数量和价格（快照）
    #[ORM\Column(type: 'integer')]
    private int $quantity;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $unitPrice;  // 单位结算价格

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $grossAmount;  // 小计 = quantity * unitPrice

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $commissionRate;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $commissionAmount;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $netAmount;  // grossAmount - commissionAmount

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSettlement(): Settlement
    {
        return $this->settlement;
    }

    public function setSettlement(Settlement $settlement): static
    {
        $this->settlement = $settlement;

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

    public function getSkuCode(): ?string
    {
        return $this->skuCode;
    }

    public function setSkuCode(?string $skuCode): static
    {
        $this->skuCode = $skuCode;

        return $this;
    }

    public function getProductName(): ?string
    {
        return $this->productName;
    }

    public function setProductName(?string $productName): static
    {
        $this->productName = $productName;

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

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getGrossAmount(): string
    {
        return $this->grossAmount;
    }

    public function setGrossAmount(string $grossAmount): static
    {
        $this->grossAmount = $grossAmount;

        return $this;
    }

    public function getCommissionRate(): string
    {
        return $this->commissionRate;
    }

    public function setCommissionRate(string $commissionRate): static
    {
        $this->commissionRate = $commissionRate;

        return $this;
    }

    public function getCommissionAmount(): string
    {
        return $this->commissionAmount;
    }

    public function setCommissionAmount(string $commissionAmount): static
    {
        $this->commissionAmount = $commissionAmount;

        return $this;
    }

    public function getNetAmount(): string
    {
        return $this->netAmount;
    }

    public function setNetAmount(string $netAmount): static
    {
        $this->netAmount = $netAmount;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * 从履约单项快照结算信息.
     */
    public function snapshotFromFulfillmentItem(FulfillmentItem $item, string $commissionRate): void
    {
        $this->fulfillmentItem = $item;
        $this->quantity = $item->getQuantity();
        $this->unitPrice = $item->getSettlementPrice() ?? '0.00';
        $this->commissionRate = $commissionRate;

        // 计算金额
        $this->grossAmount = bcmul($this->unitPrice, (string) $this->quantity, 2);
        $this->commissionAmount = bcmul($this->grossAmount, bcdiv($commissionRate, '100', 4), 2);
        $this->netAmount = bcsub($this->grossAmount, $this->commissionAmount, 2);

        // 从 OrderItem 快照商品信息
        $orderItem = $item->getOrderItem();
        $this->skuCode = $orderItem->getSkuCode();
        $this->productName = $orderItem->getExternalProductName();
    }
}
