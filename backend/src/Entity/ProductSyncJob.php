<?php

namespace App\Entity;

use App\Repository\ProductSyncJobRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: ProductSyncJobRepository::class)]
#[ORM\Table(name: 'product_sync_jobs')]
#[ORM\Index(name: 'idx_sync_job_provider', columns: ['provider'])]
#[ORM\Index(name: 'idx_sync_job_status', columns: ['status'])]
#[ORM\Index(name: 'idx_sync_job_created', columns: ['created_at'])]
class ProductSyncJob
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(length: 50)]
    private string $provider;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'total_pages', type: 'integer', nullable: true)]
    private ?int $totalPages = null;

    #[ORM\Column(name: 'processed_pages', type: 'integer', options: ['default' => 0])]
    private int $processedPages = 0;

    #[ORM\Column(name: 'total_products', type: 'integer', nullable: true)]
    private ?int $totalProducts = null;

    #[ORM\Column(name: 'synced_products', type: 'integer', options: ['default' => 0])]
    private int $syncedProducts = 0;

    #[ORM\Column(name: 'created_products', type: 'integer', options: ['default' => 0])]
    private int $createdProducts = 0;

    #[ORM\Column(name: 'updated_products', type: 'integer', options: ['default' => 0])]
    private int $updatedProducts = 0;

    #[ORM\Column(name: 'skipped_products', type: 'integer', options: ['default' => 0])]
    private int $skippedProducts = 0;

    #[ORM\Column(name: 'failed_products', type: 'integer', options: ['default' => 0])]
    private int $failedProducts = 0;

    #[ORM\Column(name: 'error_message', type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(name: 'started_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'completed_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $provider)
    {
        $this->id = (string) new Ulid();
        $this->provider = $provider;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getProvider(): string
    {
        return $this->provider;
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

    public function getTotalPages(): ?int
    {
        return $this->totalPages;
    }

    public function setTotalPages(?int $totalPages): static
    {
        $this->totalPages = $totalPages;

        return $this;
    }

    public function getProcessedPages(): int
    {
        return $this->processedPages;
    }

    public function incrementProcessedPages(): static
    {
        ++$this->processedPages;

        return $this;
    }

    public function getTotalProducts(): ?int
    {
        return $this->totalProducts;
    }

    public function setTotalProducts(?int $totalProducts): static
    {
        $this->totalProducts = $totalProducts;

        return $this;
    }

    public function getSyncedProducts(): int
    {
        return $this->syncedProducts;
    }

    public function incrementSyncedProducts(): static
    {
        ++$this->syncedProducts;

        return $this;
    }

    public function getCreatedProducts(): int
    {
        return $this->createdProducts;
    }

    public function incrementCreatedProducts(): static
    {
        ++$this->createdProducts;

        return $this;
    }

    public function getUpdatedProducts(): int
    {
        return $this->updatedProducts;
    }

    public function incrementUpdatedProducts(): static
    {
        ++$this->updatedProducts;

        return $this;
    }

    public function getSkippedProducts(): int
    {
        return $this->skippedProducts;
    }

    public function incrementSkippedProducts(): static
    {
        ++$this->skippedProducts;

        return $this;
    }

    public function getFailedProducts(): int
    {
        return $this->failedProducts;
    }

    public function incrementFailedProducts(): static
    {
        ++$this->failedProducts;

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

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): static
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    // Status helpers

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function start(): static
    {
        $this->status = self::STATUS_RUNNING;
        $this->startedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this;
    }

    public function complete(): static
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this;
    }

    public function fail(string $errorMessage): static
    {
        $this->status = self::STATUS_FAILED;
        $this->errorMessage = $errorMessage;
        $this->completedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this;
    }

    /**
     * Check if all pages have been processed.
     */
    public function isAllPagesProcessed(): bool
    {
        return $this->totalPages !== null && $this->processedPages >= $this->totalPages;
    }

    /**
     * Get progress percentage.
     */
    public function getProgressPercentage(): float
    {
        if ($this->totalPages === null || $this->totalPages === 0) {
            return 0.0;
        }

        return round(($this->processedPages / $this->totalPages) * 100, 2);
    }
}
