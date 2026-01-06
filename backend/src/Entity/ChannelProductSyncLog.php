<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SyncTriggerSource;
use App\Repository\ChannelProductSyncLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Channel Product Sync Log.
 *
 * Records every synchronization operation for observability and debugging.
 */
#[ORM\Entity(repositoryClass: ChannelProductSyncLogRepository::class)]
#[ORM\Table(name: 'channel_product_sync_logs')]
#[ORM\Index(name: 'idx_channel_product', columns: ['channel_product_id'])]
#[ORM\Index(name: 'idx_sales_channel', columns: ['sales_channel_id'])]
#[ORM\Index(name: 'idx_status', columns: ['status'])]
#[ORM\Index(name: 'idx_trigger_merchant', columns: ['trigger_merchant_id'])]
#[ORM\Index(name: 'idx_trigger_listing', columns: ['trigger_listing_id'])]
#[ORM\Index(name: 'idx_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_operation_status', columns: ['operation', 'status'])]
#[ORM\Index(name: 'idx_started_at', columns: ['started_at'])]
class ChannelProductSyncLog
{
    // Operation types
    public const OPERATION_AGGREGATE = 'aggregate';
    public const OPERATION_PUSH_PRODUCT = 'push_product';
    public const OPERATION_UPDATE_STOCK_PRICE = 'update_stock_price';
    public const OPERATION_DELIST = 'delist';

    // Status types
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(type: 'string', length: 26)]
    private string $channelProductId;

    #[ORM\Column(type: 'string', length: 26)]
    private string $salesChannelId;

    #[ORM\Column(type: 'string', length: 50)]
    private string $operation;

    #[ORM\Column(type: 'string', length: 50)]
    private string $triggerSource;

    #[ORM\Column(type: 'string', length: 26, nullable: true)]
    private ?string $triggerListingId = null;

    #[ORM\Column(type: 'string', length: 26, nullable: true)]
    private ?string $triggerMerchantId = null;

    #[ORM\Column(type: 'string', length: 26, nullable: true)]
    private ?string $triggerInventoryId = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $beforeData = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $afterData = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $errorCode = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $externalResponse = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'integer', nullable: true, options: ['unsigned' => true])]
    private ?int $durationMs = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->createdAt = $now;
        $this->startedAt = $now;
        $this->status = self::STATUS_PENDING;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getChannelProductId(): string
    {
        return $this->channelProductId;
    }

    public function setChannelProductId(string $channelProductId): static
    {
        $this->channelProductId = $channelProductId;

        return $this;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): static
    {
        $this->salesChannelId = $salesChannelId;

        return $this;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function setOperation(string $operation): static
    {
        $this->operation = $operation;

        return $this;
    }

    public function getTriggerSource(): string
    {
        return $this->triggerSource;
    }

    public function setTriggerSource(string|SyncTriggerSource $triggerSource): static
    {
        $this->triggerSource = $triggerSource instanceof SyncTriggerSource
            ? $triggerSource->value
            : $triggerSource;

        return $this;
    }

    public function getTriggerListingId(): ?string
    {
        return $this->triggerListingId;
    }

    public function setTriggerListingId(?string $triggerListingId): static
    {
        $this->triggerListingId = $triggerListingId;

        return $this;
    }

    public function getTriggerMerchantId(): ?string
    {
        return $this->triggerMerchantId;
    }

    public function setTriggerMerchantId(?string $triggerMerchantId): static
    {
        $this->triggerMerchantId = $triggerMerchantId;

        return $this;
    }

    public function getTriggerInventoryId(): ?string
    {
        return $this->triggerInventoryId;
    }

    public function setTriggerInventoryId(?string $triggerInventoryId): static
    {
        $this->triggerInventoryId = $triggerInventoryId;

        return $this;
    }

    public function getBeforeData(): ?array
    {
        return $this->beforeData;
    }

    public function setBeforeData(?array $beforeData): static
    {
        $this->beforeData = $beforeData;

        return $this;
    }

    public function getAfterData(): ?array
    {
        return $this->afterData;
    }

    public function setAfterData(?array $afterData): static
    {
        $this->afterData = $afterData;

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

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function setErrorCode(?string $errorCode): static
    {
        $this->errorCode = $errorCode;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getExternalResponse(): ?array
    {
        return $this->externalResponse;
    }

    public function setExternalResponse(?array $externalResponse): static
    {
        $this->externalResponse = $externalResponse;

        return $this;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    public function setDurationMs(?int $durationMs): static
    {
        $this->durationMs = $durationMs;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Mark as processing.
     */
    public function markProcessing(): static
    {
        $this->status = self::STATUS_PROCESSING;
        $this->startedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this;
    }

    /**
     * Mark as success with duration calculation.
     */
    public function markSuccess(?array $afterData = null): static
    {
        $this->status = self::STATUS_SUCCESS;
        $this->completedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->durationMs = $this->calculateDuration();

        if ($afterData !== null) {
            $this->afterData = $afterData;
        }

        return $this;
    }

    /**
     * Mark as failed with error details.
     */
    public function markFailed(string $errorMessage, ?string $errorCode = null, ?array $externalResponse = null): static
    {
        $this->status = self::STATUS_FAILED;
        $this->completedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->durationMs = $this->calculateDuration();
        $this->errorMessage = $errorMessage;
        $this->errorCode = $errorCode;
        $this->externalResponse = $externalResponse;

        return $this;
    }

    /**
     * Mark as skipped.
     */
    public function markSkipped(string $reason): static
    {
        $this->status = self::STATUS_SKIPPED;
        $this->completedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->durationMs = $this->calculateDuration();
        $this->errorMessage = $reason;

        return $this;
    }

    /**
     * Check if sync was successful.
     */
    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * Check if sync failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Calculate duration in milliseconds.
     */
    private function calculateDuration(): int
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $diff = $now->getTimestamp() - $this->startedAt->getTimestamp();
        $microDiff = ((int) $now->format('u') - (int) $this->startedAt->format('u')) / 1000;

        return (int) (($diff * 1000) + $microDiff);
    }

    /**
     * Create a log for aggregate operation.
     */
    public static function createForAggregate(
        ChannelProduct $channelProduct,
        SyncTriggerSource $triggerSource,
        ?string $triggerListingId = null,
        ?string $triggerMerchantId = null,
        ?string $triggerInventoryId = null,
    ): self {
        $log = new self();
        $log->setChannelProductId($channelProduct->getId());
        $log->setSalesChannelId($channelProduct->getSalesChannel()->getId());
        $log->setOperation(self::OPERATION_AGGREGATE);
        $log->setTriggerSource($triggerSource);
        $log->setTriggerListingId($triggerListingId);
        $log->setTriggerMerchantId($triggerMerchantId);
        $log->setTriggerInventoryId($triggerInventoryId);
        $log->setBeforeData([
            'price' => $channelProduct->getPlatformPrice(),
            'stock' => $channelProduct->getStockQuantity(),
            'status' => $channelProduct->getStatus(),
            'syncStatus' => $channelProduct->getSyncStatus(),
        ]);

        return $log;
    }

    /**
     * Create a log for push operation.
     */
    public static function createForPush(
        ChannelProduct $channelProduct,
        string $operation,
    ): self {
        $log = new self();
        $log->setChannelProductId($channelProduct->getId());
        $log->setSalesChannelId($channelProduct->getSalesChannel()->getId());
        $log->setOperation($operation);
        $log->setTriggerSource(SyncTriggerSource::MANUAL->value);
        $log->setBeforeData([
            'price' => $channelProduct->getPlatformPrice(),
            'stock' => $channelProduct->getStockQuantity(),
            'status' => $channelProduct->getStatus(),
            'syncStatus' => $channelProduct->getSyncStatus(),
            'externalId' => $channelProduct->getExternalId(),
        ]);

        return $log;
    }
}
