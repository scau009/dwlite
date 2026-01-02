<?php

namespace App\Entity;

use App\Repository\InventoryTransactionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 库存流水 - 记录每一次库存变动.
 */
#[ORM\Entity(repositoryClass: InventoryTransactionRepository::class)]
#[ORM\Table(name: 'inventory_transactions')]
#[ORM\Index(name: 'idx_inv_trans_inventory', columns: ['merchant_inventory_id'])]
#[ORM\Index(name: 'idx_inv_trans_type', columns: ['type'])]
#[ORM\Index(name: 'idx_inv_trans_reference', columns: ['referenceType', 'referenceId'])]
#[ORM\Index(name: 'idx_inv_trans_created', columns: ['createdAt'])]
class InventoryTransaction
{
    // 变动类型
    public const TYPE_INBOUND_TRANSIT = 'inbound_transit';       // 入库在途（发货时）
    public const TYPE_INBOUND_STOCK = 'inbound_stock';           // 入库上架（仓库收货后）
    public const TYPE_INBOUND_DAMAGED = 'inbound_damaged';       // 入库损坏
    public const TYPE_INBOUND_SHORTAGE = 'inbound_shortage';     // 入库缺货（差异清除）
    public const TYPE_OUTBOUND_RESERVE = 'outbound_reserve';     // 出库锁定（订单占用）
    public const TYPE_OUTBOUND_RELEASE = 'outbound_release';     // 出库释放（订单取消）
    public const TYPE_OUTBOUND_SHIP = 'outbound_ship';           // 出库发货
    public const TYPE_ADJUSTMENT_ADD = 'adjustment_add';         // 盘点增加
    public const TYPE_ADJUSTMENT_SUB = 'adjustment_sub';         // 盘点减少
    public const TYPE_TRANSFER_OUT = 'transfer_out';             // 调拨出库
    public const TYPE_TRANSFER_IN = 'transfer_in';               // 调拨入库
    public const TYPE_DAMAGE = 'damage';                         // 损坏报废
    public const TYPE_RETURN_INBOUND = 'return_inbound';         // 退货入库

    // 关联单据类型
    public const REF_INBOUND_ORDER = 'inbound_order';            // 送仓单
    public const REF_OUTBOUND_ORDER = 'outbound_order';          // 出库单
    public const REF_SALES_ORDER = 'sales_order';                // 销售订单
    public const REF_RETURN_ORDER = 'return_order';              // 退货单
    public const REF_ADJUSTMENT = 'adjustment';                  // 盘点单
    public const REF_TRANSFER = 'transfer';                      // 调拨单

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: MerchantInventory::class, inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: false)]
    private MerchantInventory $merchantInventory;

    #[ORM\Column(length: 30)]
    private string $type;  // 变动类型

    // 数量变动（正数增加，负数减少）
    #[ORM\Column(type: 'integer')]
    private int $quantity;

    // 变动前后余额（根据变动影响的库存类型）
    #[ORM\Column(length: 20)]
    private string $stockType;  // 影响的库存类型：in_transit, available, reserved, damaged

    #[ORM\Column(type: 'integer')]
    private int $balanceBefore;  // 变动前余额

    #[ORM\Column(type: 'integer')]
    private int $balanceAfter;  // 变动后余额

    // 关联单据
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $referenceType = null;  // 关联单据类型

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $referenceId = null;  // 关联单据 ID

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $referenceNo = null;  // 关联单据编号（便于展示）

    // 成本信息
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $unitCost = null;  // 单件成本

    // 操作信息
    #[ORM\Column(length: 26, nullable: true)]
    private ?string $operatorId = null;  // 操作人 ID

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $operatorName = null;  // 操作人名称

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;  // 备注

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

    public function getMerchantInventory(): MerchantInventory
    {
        return $this->merchantInventory;
    }

    public function setMerchantInventory(MerchantInventory $merchantInventory): static
    {
        $this->merchantInventory = $merchantInventory;

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

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getStockType(): string
    {
        return $this->stockType;
    }

    public function setStockType(string $stockType): static
    {
        $this->stockType = $stockType;

        return $this;
    }

    public function getBalanceBefore(): int
    {
        return $this->balanceBefore;
    }

    public function setBalanceBefore(int $balanceBefore): static
    {
        $this->balanceBefore = $balanceBefore;

        return $this;
    }

    public function getBalanceAfter(): int
    {
        return $this->balanceAfter;
    }

    public function setBalanceAfter(int $balanceAfter): static
    {
        $this->balanceAfter = $balanceAfter;

        return $this;
    }

    public function getReferenceType(): ?string
    {
        return $this->referenceType;
    }

    public function setReferenceType(?string $referenceType): static
    {
        $this->referenceType = $referenceType;

        return $this;
    }

    public function getReferenceId(): ?string
    {
        return $this->referenceId;
    }

    public function setReferenceId(?string $referenceId): static
    {
        $this->referenceId = $referenceId;

        return $this;
    }

    public function getReferenceNo(): ?string
    {
        return $this->referenceNo;
    }

    public function setReferenceNo(?string $referenceNo): static
    {
        $this->referenceNo = $referenceNo;

        return $this;
    }

    public function getUnitCost(): ?string
    {
        return $this->unitCost;
    }

    public function setUnitCost(?string $unitCost): static
    {
        $this->unitCost = $unitCost;

        return $this;
    }

    public function getOperatorId(): ?string
    {
        return $this->operatorId;
    }

    public function setOperatorId(?string $operatorId): static
    {
        $this->operatorId = $operatorId;

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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    // 便捷方法

    public function isInbound(): bool
    {
        return in_array($this->type, [
            self::TYPE_INBOUND_TRANSIT,
            self::TYPE_INBOUND_STOCK,
            self::TYPE_TRANSFER_IN,
            self::TYPE_RETURN_INBOUND,
        ], true);
    }

    public function isOutbound(): bool
    {
        return in_array($this->type, [
            self::TYPE_OUTBOUND_SHIP,
            self::TYPE_TRANSFER_OUT,
            self::TYPE_DAMAGE,
        ], true);
    }

    /**
     * 获取类型的中文描述.
     */
    public function getTypeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_INBOUND_TRANSIT => '入库在途',
            self::TYPE_INBOUND_STOCK => '入库上架',
            self::TYPE_INBOUND_DAMAGED => '入库损坏',
            self::TYPE_INBOUND_SHORTAGE => '入库缺货',
            self::TYPE_OUTBOUND_RESERVE => '订单锁定',
            self::TYPE_OUTBOUND_RELEASE => '订单释放',
            self::TYPE_OUTBOUND_SHIP => '出库发货',
            self::TYPE_ADJUSTMENT_ADD => '盘点增加',
            self::TYPE_ADJUSTMENT_SUB => '盘点减少',
            self::TYPE_TRANSFER_OUT => '调拨出库',
            self::TYPE_TRANSFER_IN => '调拨入库',
            self::TYPE_DAMAGE => '损坏报废',
            self::TYPE_RETURN_INBOUND => '退货入库',
            default => $this->type,
        };
    }

    /**
     * 创建入库在途流水.
     */
    public static function createInboundTransit(
        MerchantInventory $inventory,
        int $quantity,
        string $referenceId,
        string $referenceNo,
        ?string $notes = null
    ): self {
        $transaction = new self();
        $transaction->merchantInventory = $inventory;
        $transaction->type = self::TYPE_INBOUND_TRANSIT;
        $transaction->quantity = $quantity;
        $transaction->stockType = 'in_transit';
        $transaction->balanceBefore = $inventory->getQuantityInTransit();
        $transaction->balanceAfter = $inventory->getQuantityInTransit() + $quantity;
        $transaction->referenceType = self::REF_INBOUND_ORDER;
        $transaction->referenceId = $referenceId;
        $transaction->referenceNo = $referenceNo;
        $transaction->notes = $notes;

        return $transaction;
    }

    /**
     * 创建入库上架流水.
     */
    public static function createInboundStock(
        MerchantInventory $inventory,
        int $quantity,
        string $referenceId,
        string $referenceNo,
        ?string $unitCost = null,
        ?string $notes = null
    ): self {
        $transaction = new self();
        $transaction->merchantInventory = $inventory;
        $transaction->type = self::TYPE_INBOUND_STOCK;
        $transaction->quantity = $quantity;
        $transaction->stockType = 'available';
        $transaction->balanceBefore = $inventory->getQuantityAvailable();
        $transaction->balanceAfter = $inventory->getQuantityAvailable() + $quantity;
        $transaction->referenceType = self::REF_INBOUND_ORDER;
        $transaction->referenceId = $referenceId;
        $transaction->referenceNo = $referenceNo;
        $transaction->unitCost = $unitCost;
        $transaction->notes = $notes;

        return $transaction;
    }
}
