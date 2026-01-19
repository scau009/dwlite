<?php

namespace App\Entity;

use App\Repository\WebhookRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: WebhookRepository::class)]
#[ORM\Table(name: 'webhooks')]
#[ORM\HasLifecycleCallbacks]
class Webhook
{
    // Webhook 状态
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_FAILED = 'failed';

    // 仓库事件
    public const EVENT_INBOUND_ORDER_CREATED = 'inbound.order.created';
    public const EVENT_INBOUND_ORDER_SHIPPED = 'inbound.order.shipped';
    public const EVENT_OUTBOUND_ORDER_CREATED = 'outbound.order.created';
    public const EVENT_OUTBOUND_ORDER_CANCELLED = 'outbound.order.cancelled';

    // 商户事件
    public const EVENT_FULFILLMENT_CREATED = 'fulfillment.created';
    public const EVENT_FULFILLMENT_DEADLINE_APPROACHING = 'fulfillment.deadline_approaching';
    public const EVENT_FULFILLMENT_EXPIRED = 'fulfillment.expired';
    public const EVENT_SETTLEMENT_CREATED = 'settlement.created';
    public const EVENT_SETTLEMENT_COMPLETED = 'settlement.completed';
    public const EVENT_INBOUND_RECEIVING_COMPLETED = 'inbound.receiving_completed';
    public const EVENT_INBOUND_EXCEPTION_REPORTED = 'inbound.exception_reported';

    // 最大失败次数
    public const MAX_FAILURE_COUNT = 5;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: ApiKey::class, inversedBy: 'webhooks')]
    #[ORM\JoinColumn(name: 'api_key_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ApiKey $apiKey;

    #[ORM\Column(type: 'string', length: 500)]
    private string $url;

    /** @var array<string> */
    #[ORM\Column(type: 'json')]
    private array $events = [];

    #[ORM\Column(type: 'string', length: 64)]
    private string $secret;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: 'smallint', options: ['unsigned' => true, 'default' => 0])]
    private int $failureCount = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastTriggeredAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSuccessAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, WebhookDelivery> */
    #[ORM\OneToMany(targetEntity: WebhookDelivery::class, mappedBy: 'webhook', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $deliveries;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->secret = bin2hex(random_bytes(32));
        $this->deliveries = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * Get all available warehouse events.
     *
     * @return array<string>
     */
    public static function getWarehouseEvents(): array
    {
        return [
            self::EVENT_INBOUND_ORDER_CREATED,
            self::EVENT_INBOUND_ORDER_SHIPPED,
            self::EVENT_OUTBOUND_ORDER_CREATED,
            self::EVENT_OUTBOUND_ORDER_CANCELLED,
        ];
    }

    /**
     * Get all available merchant events.
     *
     * @return array<string>
     */
    public static function getMerchantEvents(): array
    {
        return [
            self::EVENT_FULFILLMENT_CREATED,
            self::EVENT_FULFILLMENT_DEADLINE_APPROACHING,
            self::EVENT_FULFILLMENT_EXPIRED,
            self::EVENT_SETTLEMENT_CREATED,
            self::EVENT_SETTLEMENT_COMPLETED,
            self::EVENT_INBOUND_RECEIVING_COMPLETED,
            self::EVENT_INBOUND_EXCEPTION_REPORTED,
        ];
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getApiKey(): ApiKey
    {
        return $this->apiKey;
    }

    public function setApiKey(ApiKey $apiKey): static
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * @param array<string> $events
     */
    public function setEvents(array $events): static
    {
        $this->events = $events;

        return $this;
    }

    public function hasEvent(string $event): bool
    {
        return in_array($event, $this->events, true);
    }

    public function addEvent(string $event): static
    {
        if (!in_array($event, $this->events, true)) {
            $this->events[] = $event;
        }

        return $this;
    }

    public function removeEvent(string $event): static
    {
        $this->events = array_values(array_filter(
            $this->events,
            fn ($e) => $e !== $event
        ));

        return $this;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function setSecret(string $secret): static
    {
        $this->secret = $secret;

        return $this;
    }

    public function regenerateSecret(): static
    {
        $this->secret = bin2hex(random_bytes(32));
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

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

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function suspend(): static
    {
        $this->status = self::STATUS_SUSPENDED;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this;
    }

    public function activate(): static
    {
        $this->status = self::STATUS_ACTIVE;
        $this->failureCount = 0;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this;
    }

    public function markAsFailed(): static
    {
        $this->status = self::STATUS_FAILED;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this;
    }

    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    public function incrementFailureCount(): static
    {
        ++$this->failureCount;
        if ($this->failureCount >= self::MAX_FAILURE_COUNT) {
            $this->markAsFailed();
        }

        return $this;
    }

    public function resetFailureCount(): static
    {
        $this->failureCount = 0;

        return $this;
    }

    public function getLastTriggeredAt(): ?\DateTimeImmutable
    {
        return $this->lastTriggeredAt;
    }

    public function setLastTriggeredAt(?\DateTimeImmutable $lastTriggeredAt): static
    {
        $this->lastTriggeredAt = $lastTriggeredAt;

        return $this;
    }

    public function updateLastTriggeredAt(): static
    {
        $this->lastTriggeredAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this;
    }

    public function getLastSuccessAt(): ?\DateTimeImmutable
    {
        return $this->lastSuccessAt;
    }

    public function setLastSuccessAt(?\DateTimeImmutable $lastSuccessAt): static
    {
        $this->lastSuccessAt = $lastSuccessAt;

        return $this;
    }

    public function markSuccess(): static
    {
        $this->lastSuccessAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->failureCount = 0;

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
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * @return Collection<int, WebhookDelivery>
     */
    public function getDeliveries(): Collection
    {
        return $this->deliveries;
    }

    public function addDelivery(WebhookDelivery $delivery): static
    {
        if (!$this->deliveries->contains($delivery)) {
            $this->deliveries->add($delivery);
            $delivery->setWebhook($this);
        }

        return $this;
    }

    /**
     * Sign a payload using HMAC-SHA256.
     */
    public function signPayload(string $payload): string
    {
        return 'sha256='.hash_hmac('sha256', $payload, $this->secret);
    }
}
