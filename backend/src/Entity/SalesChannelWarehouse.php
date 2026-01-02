<?php

namespace App\Entity;

use App\Repository\SalesChannelWarehouseRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: SalesChannelWarehouseRepository::class)]
#[ORM\Table(name: 'sales_channel_warehouses')]
#[ORM\UniqueConstraint(name: 'uk_channel_warehouse', columns: ['sales_channel_id', 'warehouse_id'])]
#[ORM\Index(name: 'idx_scw_channel', columns: ['sales_channel_id'])]
#[ORM\Index(name: 'idx_scw_warehouse', columns: ['warehouse_id'])]
#[ORM\Index(name: 'idx_scw_priority', columns: ['priority'])]
#[ORM\Index(name: 'idx_scw_status', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class SalesChannelWarehouse
{
    // 状态常量
    public const STATUS_ACTIVE = 'active';      // 激活
    public const STATUS_DISABLED = 'disabled';  // 禁用

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: SalesChannel::class, inversedBy: 'channelWarehouses')]
    #[ORM\JoinColumn(name: 'sales_channel_id', nullable: false, onDelete: 'CASCADE')]
    private SalesChannel $salesChannel;

    #[ORM\ManyToOne(targetEntity: Warehouse::class, inversedBy: 'channelWarehouses')]
    #[ORM\JoinColumn(name: 'warehouse_id', nullable: false, onDelete: 'CASCADE')]
    private Warehouse $warehouse;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $priority = 0;  // 优先级，数字越小优先级越高

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $remark = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
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

    public function getWarehouse(): Warehouse
    {
        return $this->warehouse;
    }

    public function setWarehouse(Warehouse $warehouse): static
    {
        $this->warehouse = $warehouse;

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
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    // 便捷方法

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isDisabled(): bool
    {
        return $this->status === self::STATUS_DISABLED;
    }

    public function activate(): static
    {
        $this->status = self::STATUS_ACTIVE;

        return $this;
    }

    public function disable(): static
    {
        $this->status = self::STATUS_DISABLED;

        return $this;
    }
}