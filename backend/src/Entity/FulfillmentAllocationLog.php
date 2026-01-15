<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FulfillmentAllocationLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 履约分配日志 - 记录每次分配尝试的详细信息.
 */
#[ORM\Entity(repositoryClass: FulfillmentAllocationLogRepository::class)]
#[ORM\Table(name: 'fulfillment_allocation_logs')]
#[ORM\Index(name: 'idx_fal_order', columns: ['order_id'])]
#[ORM\Index(name: 'idx_fal_order_item', columns: ['order_item_id'])]
#[ORM\Index(name: 'idx_fal_merchant', columns: ['selected_merchant_id'])]
#[ORM\Index(name: 'idx_fal_result', columns: ['result'])]
#[ORM\Index(name: 'idx_fal_created', columns: ['created_at'])]
class FulfillmentAllocationLog
{
    // 分配结果
    public const RESULT_SUCCESS = 'success';           // 分配成功
    public const RESULT_NO_STOCK = 'no_stock';         // 库存不足
    public const RESULT_PRICE_INVALID = 'price_invalid'; // 价格校验失败
    public const RESULT_REJECTED = 'rejected';         // 商户拒绝
    public const RESULT_EXPIRED = 'expired';           // 超时未响应
    public const RESULT_NO_SOURCE = 'no_source';       // 无可用来源

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\ManyToOne(targetEntity: OrderItem::class)]
    #[ORM\JoinColumn(name: 'order_item_id', nullable: true, onDelete: 'CASCADE')]
    private ?OrderItem $orderItem = null;

    // 尝试次数
    #[ORM\Column(type: 'integer')]
    private int $attemptNumber;

    // 选中的商户ID（如果分配成功）
    #[ORM\Column(type: 'string', length: 26, nullable: true)]
    private ?string $selectedMerchantId = null;

    // 选中的来源ID（如果分配成功）
    #[ORM\Column(type: 'string', length: 26, nullable: true)]
    private ?string $selectedSourceId = null;

    // 分配结果
    #[ORM\Column(type: 'string', length: 20)]
    private string $result;

    // 所有候选来源及评分（JSON格式）
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $candidateSources = null;

    // 失败原因
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $failureReason = null;

    // 创建时间
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

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function setOrder(Order $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function getOrderItem(): ?OrderItem
    {
        return $this->orderItem;
    }

    public function setOrderItem(?OrderItem $orderItem): static
    {
        $this->orderItem = $orderItem;

        return $this;
    }

    public function getAttemptNumber(): int
    {
        return $this->attemptNumber;
    }

    public function setAttemptNumber(int $attemptNumber): static
    {
        $this->attemptNumber = $attemptNumber;

        return $this;
    }

    public function getSelectedMerchantId(): ?string
    {
        return $this->selectedMerchantId;
    }

    public function setSelectedMerchantId(?string $selectedMerchantId): static
    {
        $this->selectedMerchantId = $selectedMerchantId;

        return $this;
    }

    public function getSelectedSourceId(): ?string
    {
        return $this->selectedSourceId;
    }

    public function setSelectedSourceId(?string $selectedSourceId): static
    {
        $this->selectedSourceId = $selectedSourceId;

        return $this;
    }

    public function getResult(): string
    {
        return $this->result;
    }

    public function setResult(string $result): static
    {
        $this->result = $result;

        return $this;
    }

    /**
     * @return array<array{sourceId: string, merchantId: string, score: float, available: int, price: string}>|null
     */
    public function getCandidateSources(): ?array
    {
        return $this->candidateSources;
    }

    /**
     * @param array<array{sourceId: string, merchantId: string, score: float, available: int, price: string}>|null $candidateSources
     */
    public function setCandidateSources(?array $candidateSources): static
    {
        $this->candidateSources = $candidateSources;

        return $this;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): static
    {
        $this->failureReason = $failureReason;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    // 便捷方法

    public function isSuccess(): bool
    {
        return $this->result === self::RESULT_SUCCESS;
    }

    /**
     * 创建成功日志的工厂方法.
     */
    public static function createSuccess(
        Order $order,
        ?OrderItem $orderItem,
        int $attemptNumber,
        string $merchantId,
        string $sourceId,
        ?array $candidates = null
    ): self {
        $log = new self();
        $log->setOrder($order);
        $log->setOrderItem($orderItem);
        $log->setAttemptNumber($attemptNumber);
        $log->setSelectedMerchantId($merchantId);
        $log->setSelectedSourceId($sourceId);
        $log->setResult(self::RESULT_SUCCESS);
        $log->setCandidateSources($candidates);

        return $log;
    }

    /**
     * 创建失败日志的工厂方法.
     */
    public static function createFailure(
        Order $order,
        ?OrderItem $orderItem,
        int $attemptNumber,
        string $result,
        string $failureReason,
        ?array $candidates = null
    ): self {
        $log = new self();
        $log->setOrder($order);
        $log->setOrderItem($orderItem);
        $log->setAttemptNumber($attemptNumber);
        $log->setResult($result);
        $log->setFailureReason($failureReason);
        $log->setCandidateSources($candidates);

        return $log;
    }
}
