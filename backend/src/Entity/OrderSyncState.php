<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrderSyncStateRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 订单同步状态 - 独立管理订单与渠道的同步状态.
 *
 * 与 Order 一对一关联，不污染 Order 的业务语义。
 */
#[ORM\Entity(repositoryClass: OrderSyncStateRepository::class)]
#[ORM\Table(name: 'order_sync_states')]
#[ORM\Index(name: 'idx_pending_op', columns: ['pending_operation', 'retry_count', 'last_attempt_at'])]
#[ORM\HasLifecycleCallbacks]
class OrderSyncState
{
    // 待执行操作常量
    public const OP_CONFIRM = 'confirm';
    public const OP_SHIP = 'ship';
    public const OP_CANCEL = 'cancel';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\OneToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    // 一次性操作标记
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isPulled = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isConfirmed = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isCancelled = false;

    // 发货可多次，独立追踪
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $shippedSyncCount = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastShippedSyncAt = null;

    // 补偿扫描用
    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $pendingOperation = null;

    // 重试相关
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $retryCount = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastAttemptAt = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $lastErrorCode = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $lastErrorMessage = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Order $order)
    {
        $this->id = (string) new Ulid();
        $this->order = $order;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function isPulled(): bool
    {
        return $this->isPulled;
    }

    public function markPulled(): static
    {
        $this->isPulled = true;
        $this->clearPendingIfMatches(null); // 拉取不是待执行操作

        return $this;
    }

    public function isConfirmed(): bool
    {
        return $this->isConfirmed;
    }

    public function markConfirmed(): static
    {
        $this->isConfirmed = true;
        $this->clearPendingIfMatches(self::OP_CONFIRM);
        $this->resetRetry();

        return $this;
    }

    public function isCancelled(): bool
    {
        return $this->isCancelled;
    }

    public function markCancelled(): static
    {
        $this->isCancelled = true;
        $this->clearPendingIfMatches(self::OP_CANCEL);
        $this->resetRetry();

        return $this;
    }

    public function getShippedSyncCount(): int
    {
        return $this->shippedSyncCount;
    }

    public function getLastShippedSyncAt(): ?\DateTimeImmutable
    {
        return $this->lastShippedSyncAt;
    }

    public function markShipped(): static
    {
        ++$this->shippedSyncCount;
        $this->lastShippedSyncAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->clearPendingIfMatches(self::OP_SHIP);
        $this->resetRetry();

        return $this;
    }

    public function getPendingOperation(): ?string
    {
        return $this->pendingOperation;
    }

    public function setPendingOperation(?string $operation): static
    {
        $this->pendingOperation = $operation;
        if ($operation !== null) {
            $this->retryCount = 0;
        }

        return $this;
    }

    public function hasPendingOperation(): bool
    {
        return $this->pendingOperation !== null;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function getLastAttemptAt(): ?\DateTimeImmutable
    {
        return $this->lastAttemptAt;
    }

    public function getLastErrorCode(): ?string
    {
        return $this->lastErrorCode;
    }

    public function getLastErrorMessage(): ?string
    {
        return $this->lastErrorMessage;
    }

    public function recordAttempt(): static
    {
        ++$this->retryCount;
        $this->lastAttemptAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this;
    }

    public function recordFailure(string $errorMessage, ?string $errorCode = null): static
    {
        $this->lastErrorMessage = mb_substr($errorMessage, 0, 500);
        $this->lastErrorCode = $errorCode;
        $this->recordAttempt();

        return $this;
    }

    public function canRetry(int $maxRetryCount = 5): bool
    {
        return $this->retryCount < $maxRetryCount;
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

    private function clearPendingIfMatches(?string $operation): void
    {
        if ($this->pendingOperation === $operation) {
            $this->pendingOperation = null;
        }
    }

    private function resetRetry(): void
    {
        $this->retryCount = 0;
        $this->lastErrorCode = null;
        $this->lastErrorMessage = null;
    }
}
