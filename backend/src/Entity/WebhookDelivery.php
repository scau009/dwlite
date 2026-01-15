<?php

namespace App\Entity;

use App\Repository\WebhookDeliveryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: WebhookDeliveryRepository::class)]
#[ORM\Table(name: 'webhook_deliveries')]
class WebhookDelivery
{
    // 投递状态
    public const STATUS_PENDING = 'pending';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';

    // 最大重试次数
    public const MAX_ATTEMPTS = 5;

    // 重试间隔（秒）
    public const RETRY_INTERVALS = [
        1 => 60,      // 1分钟后
        2 => 300,     // 5分钟后
        3 => 1800,    // 30分钟后
        4 => 7200,    // 2小时后
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Webhook::class, inversedBy: 'deliveries')]
    #[ORM\JoinColumn(name: 'webhook_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Webhook $webhook;

    #[ORM\Column(type: 'string', length: 50)]
    private string $event;

    #[ORM\Column(type: 'string', length: 36)]
    private string $eventId;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column(type: 'smallint', nullable: true, options: ['unsigned' => true])]
    private ?int $responseCode = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $responseBody = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'smallint', options: ['unsigned' => true, 'default' => 0])]
    private int $attempts = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $nextRetryAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->eventId = (string) new Ulid();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * Create a new delivery for a webhook event.
     *
     * @param array<string, mixed> $payload
     */
    public static function create(Webhook $webhook, string $event, array $payload): self
    {
        $delivery = new self();
        $delivery->webhook = $webhook;
        $delivery->event = $event;
        $delivery->payload = $payload;
        return $delivery;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getWebhook(): Webhook
    {
        return $this->webhook;
    }

    public function setWebhook(Webhook $webhook): static
    {
        $this->webhook = $webhook;
        return $this;
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function setEvent(string $event): static
    {
        $this->event = $event;
        return $this;
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): static
    {
        $this->payload = $payload;
        return $this;
    }

    public function getResponseCode(): ?int
    {
        return $this->responseCode;
    }

    public function setResponseCode(?int $responseCode): static
    {
        $this->responseCode = $responseCode;
        return $this;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    public function setResponseBody(?string $responseBody): static
    {
        // Truncate response body to 2000 characters
        if ($responseBody !== null && mb_strlen($responseBody) > 2000) {
            $responseBody = mb_substr($responseBody, 0, 2000);
        }
        $this->responseBody = $responseBody;
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

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function incrementAttempts(): static
    {
        $this->attempts++;
        return $this;
    }

    public function canRetry(): bool
    {
        return $this->attempts < self::MAX_ATTEMPTS && $this->status !== self::STATUS_DELIVERED;
    }

    public function getNextRetryAt(): ?\DateTimeImmutable
    {
        return $this->nextRetryAt;
    }

    public function setNextRetryAt(?\DateTimeImmutable $nextRetryAt): static
    {
        $this->nextRetryAt = $nextRetryAt;
        return $this;
    }

    /**
     * Calculate and set the next retry time based on current attempts.
     */
    public function scheduleNextRetry(): static
    {
        if (!$this->canRetry()) {
            $this->nextRetryAt = null;
            return $this;
        }

        $interval = self::RETRY_INTERVALS[$this->attempts] ?? 7200;
        $this->nextRetryAt = new \DateTimeImmutable(
            sprintf('+%d seconds', $interval),
            new \DateTimeZone('UTC')
        );
        return $this;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTimeImmutable $deliveredAt): static
    {
        $this->deliveredAt = $deliveredAt;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Mark this delivery as successful.
     */
    public function markDelivered(int $responseCode, ?string $responseBody = null): static
    {
        $this->status = self::STATUS_DELIVERED;
        $this->responseCode = $responseCode;
        $this->setResponseBody($responseBody);
        $this->deliveredAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->nextRetryAt = null;
        return $this;
    }

    /**
     * Mark this delivery attempt as failed.
     */
    public function markAttemptFailed(int $responseCode, ?string $responseBody = null): static
    {
        $this->responseCode = $responseCode;
        $this->setResponseBody($responseBody);
        $this->incrementAttempts();

        if ($this->canRetry()) {
            $this->scheduleNextRetry();
        } else {
            $this->status = self::STATUS_FAILED;
            $this->nextRetryAt = null;
        }

        return $this;
    }

    /**
     * Build the full webhook payload including metadata.
     *
     * @return array<string, mixed>
     */
    public function buildFullPayload(): array
    {
        return [
            'event' => $this->event,
            'eventId' => $this->eventId,
            'timestamp' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'data' => $this->payload,
        ];
    }

    /**
     * Get the JSON-encoded full payload.
     */
    public function getJsonPayload(): string
    {
        return json_encode($this->buildFullPayload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
