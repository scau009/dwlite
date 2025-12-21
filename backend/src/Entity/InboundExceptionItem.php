<?php

namespace App\Entity;

use App\Repository\InboundExceptionItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 入库异常单明细 - 异常单中的 SKU 明细
 */
#[ORM\Entity(repositoryClass: InboundExceptionItemRepository::class)]
#[ORM\Table(name: 'inbound_exception_items')]
#[ORM\Index(name: 'idx_exception_item_exception', columns: ['inbound_exception_id'])]
#[ORM\Index(name: 'idx_exception_item_order_item', columns: ['inbound_order_item_id'])]
#[ORM\HasLifecycleCallbacks]
class InboundExceptionItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: InboundException::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private InboundException $inboundException;

    #[ORM\ManyToOne(targetEntity: InboundOrderItem::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?InboundOrderItem $inboundOrderItem = null;

    // SKU 快照字段
    #[ORM\Column(name: 'sku_name', length: 255, nullable: true)]
    private ?string $skuName = null;

    #[ORM\Column(name: 'color_name', length: 255, nullable: true)]
    private ?string $colorName = null;

    #[ORM\Column(name: 'product_name', length: 255, nullable: true)]
    private ?string $productName = null;

    #[ORM\Column(name: 'product_image', length: 500, nullable: true)]
    private ?string $productImage = null;

    #[ORM\Column(name: 'quantity', type: 'integer')]
    private int $quantity = 0;

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

    public function getInboundException(): InboundException
    {
        return $this->inboundException;
    }

    public function setInboundException(InboundException $inboundException): static
    {
        $this->inboundException = $inboundException;
        return $this;
    }

    public function getInboundOrderItem(): ?InboundOrderItem
    {
        return $this->inboundOrderItem;
    }

    public function setInboundOrderItem(?InboundOrderItem $inboundOrderItem): static
    {
        $this->inboundOrderItem = $inboundOrderItem;
        return $this;
    }

    public function getSkuName(): ?string
    {
        return $this->skuName;
    }

    public function setSkuName(?string $skuName): static
    {
        $this->skuName = $skuName;
        return $this;
    }

    public function getColorName(): ?string
    {
        return $this->colorName;
    }

    public function setColorName(?string $colorName): static
    {
        $this->colorName = $colorName;
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

    public function getProductImage(): ?string
    {
        return $this->productImage;
    }

    public function setProductImage(?string $productImage): static
    {
        $this->productImage = $productImage;
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

    /**
     * 从入库单明细快照信息
     */
    public function snapshotFromInboundOrderItem(InboundOrderItem $orderItem): void
    {
        $this->inboundOrderItem = $orderItem;
        $this->skuName = $orderItem->getSkuName();
        $this->colorName = $orderItem->getColorName();
        $this->productName = $orderItem->getProductName();
        $this->productImage = $orderItem->getProductImage();
    }
}
