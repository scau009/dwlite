<?php

namespace App\Entity;

use App\Repository\ApiKeyLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: ApiKeyLogRepository::class)]
#[ORM\Table(name: 'api_key_logs')]
class ApiKeyLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: ApiKey::class)]
    #[ORM\JoinColumn(name: 'api_key_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ApiKey $apiKey;

    #[ORM\Column(type: 'string', length: 255)]
    private string $endpoint;

    #[ORM\Column(type: 'string', length: 10)]
    private string $method;

    #[ORM\Column(type: 'string', length: 36)]
    private string $requestId;

    #[ORM\Column(type: 'string', length: 45)]
    private string $ipAddress;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'integer', nullable: true, options: ['unsigned' => true])]
    private ?int $requestBodySize = null;

    #[ORM\Column(type: 'smallint', options: ['unsigned' => true])]
    private int $statusCode;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $errorCode = null;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $responseTimeMs;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public static function create(
        ApiKey $apiKey,
        string $endpoint,
        string $method,
        string $requestId,
        string $ipAddress,
        int $statusCode,
        int $responseTimeMs,
        ?string $userAgent = null,
        ?int $requestBodySize = null,
        ?string $errorCode = null
    ): self {
        $log = new self();
        $log->apiKey = $apiKey;
        $log->endpoint = $endpoint;
        $log->method = $method;
        $log->requestId = $requestId;
        $log->ipAddress = $ipAddress;
        $log->statusCode = $statusCode;
        $log->responseTimeMs = $responseTimeMs;
        $log->userAgent = $userAgent;
        $log->requestBodySize = $requestBodySize;
        $log->errorCode = $errorCode;
        return $log;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getApiKey(): ApiKey
    {
        return $this->apiKey;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getRequestBodySize(): ?int
    {
        return $this->requestBodySize;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getResponseTimeMs(): int
    {
        return $this->responseTimeMs;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }
}
