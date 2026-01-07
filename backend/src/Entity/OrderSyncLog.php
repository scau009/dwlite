<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrderSyncLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 订单同步日志 - 记录每次同步操作的详细信息.
 */
#[ORM\Entity(repositoryClass: OrderSyncLogRepository::class)]
#[ORM\Table(name: 'order_sync_logs')]
#[ORM\Index(name: 'idx_order', columns: ['order_id'])]
#[ORM\Index(name: 'idx_sales_channel', columns: ['sales_channel_id'])]
#[ORM\Index(name: 'idx_external_order', columns: ['external_order_id'])]
#[ORM\Index(name: 'idx_direction_status', columns: ['direction', 'status'])]
#[ORM\Index(name: 'idx_created_at', columns: ['created_at'])]
class OrderSyncLog
{
    // 同步方向
    public const DIRECTION_PULL = 'pull';
    public const DIRECTION_PUSH = 'push';

    // 操作类型
    public const OP_PULL_ORDER = 'pull_order';
    public const OP_CONFIRM_ORDER = 'confirm_order';
    public const OP_SHIP_ORDER = 'ship_order';
    public const OP_CANCEL_ORDER = 'cancel_order';

    // 状态
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(type: 'string', length: 26, nullable: true)]
    private ?string $orderId = null;

    #[ORM\Column(type: 'string', length: 26)]
    private string $salesChannelId;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $externalOrderId = null;

    #[ORM\Column(type: 'string', length: 10)]
    private string $direction;

    #[ORM\Column(type: 'string', length: 50)]
    private string $operation;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $requestData = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $responseData = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $errorCode = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $retryCount = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'integer', nullable: true, options: ['unsigned' => true])]
    private ?int $durationMs = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $salesChannelId, string $direction, string $operation)
    {
        $this->id = (string) new Ulid();
        $this->salesChannelId = $salesChannelId;
        $this->direction = $direction;
        $this->operation = $operation;
        $this->status = self::STATUS_PENDING;
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->startedAt = $now;
        $this->createdAt = $now;
    }

    public static function createForPull(string $salesChannelId, ?string $externalOrderId = null): self
    {
        $log = new self($salesChannelId, self::DIRECTION_PULL, self::OP_PULL_ORDER);
        $log->externalOrderId = $externalOrderId;

        return $log;
    }

    public static function createForPush(Order $order, string $operation): self
    {
        $log = new self(
            $order->getSalesChannel()->getId(),
            self::DIRECTION_PUSH,
            $operation
        );
        $log->orderId = $order->getId();
        $log->externalOrderId = $order->getExternalOrderId();

        return $log;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): static
    {
        $this->orderId = $orderId;

        return $this;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getExternalOrderId(): ?string
    {
        return $this->externalOrderId;
    }

    public function setExternalOrderId(?string $externalOrderId): static
    {
        $this->externalOrderId = $externalOrderId;

        return $this;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getRequestData(): ?array
    {
        return $this->requestData;
    }

    public function setRequestData(?array $requestData): static
    {
        $this->requestData = $requestData;

        return $this;
    }

    public function getResponseData(): ?array
    {
        return $this->responseData;
    }

    public function setResponseData(?array $responseData): static
    {
        $this->responseData = $responseData;

        return $this;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function setRetryCount(int $retryCount): static
    {
        $this->retryCount = $retryCount;

        return $this;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function markProcessing(): static
    {
        $this->status = self::STATUS_PROCESSING;
        $this->startedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this;
    }

    public function markSuccess(?array $responseData = null): static
    {
        $this->status = self::STATUS_SUCCESS;
        $this->completedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->durationMs = $this->calculateDuration();
        $this->responseData = $responseData;

        return $this;
    }

    public function markFailed(string $errorMessage, ?string $errorCode = null, ?array $responseData = null): static
    {
        $this->status = self::STATUS_FAILED;
        $this->completedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->durationMs = $this->calculateDuration();
        $this->errorMessage = $errorMessage;
        $this->errorCode = $errorCode;
        $this->responseData = $responseData;

        return $this;
    }

    public function markSkipped(string $reason): static
    {
        $this->status = self::STATUS_SKIPPED;
        $this->completedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->durationMs = $this->calculateDuration();
        $this->responseData = ['reason' => $reason];

        return $this;
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    private function calculateDuration(): int
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $startTimestamp = (float) $this->startedAt->format('U.u');
        $endTimestamp = (float) $now->format('U.u');

        return (int) (($endTimestamp - $startTimestamp) * 1000);
    }
}
