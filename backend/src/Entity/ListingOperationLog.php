<?php

namespace App\Entity;

use App\Repository\ListingOperationLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 上架操作日志.
 *
 * 记录所有上架相关的敏感操作，包括创建、修改、激活、暂停、删除等
 */
#[ORM\Entity(repositoryClass: ListingOperationLogRepository::class)]
#[ORM\Table(name: 'listing_operation_logs')]
#[ORM\Index(name: 'idx_listing_id', columns: ['listing_id'])]
#[ORM\Index(name: 'idx_merchant_id', columns: ['merchant_id'])]
#[ORM\Index(name: 'idx_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_operation', columns: ['operation'])]
class ListingOperationLog
{
    // 操作类型
    public const OPERATION_CREATE = 'create';
    public const OPERATION_UPDATE_PRICE = 'update_price';
    public const OPERATION_UPDATE_COMPARE_PRICE = 'update_compare_price';
    public const OPERATION_UPDATE_ALLOCATION = 'update_allocation';
    public const OPERATION_UPDATE_REMARK = 'update_remark';
    public const OPERATION_ACTIVATE = 'activate';
    public const OPERATION_PAUSE = 'pause';
    public const OPERATION_DELETE = 'delete';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(type: 'string', length: 26)]
    private string $listingId;

    #[ORM\Column(type: 'string', length: 26)]
    private string $merchantId;

    #[ORM\Column(type: 'string', length: 26)]
    private string $operatorId;

    #[ORM\Column(type: 'string', length: 50)]
    private string $operation;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $changes = null;

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

    public function getListingId(): string
    {
        return $this->listingId;
    }

    public function setListingId(string $listingId): static
    {
        $this->listingId = $listingId;

        return $this;
    }

    public function getMerchantId(): string
    {
        return $this->merchantId;
    }

    public function setMerchantId(string $merchantId): static
    {
        $this->merchantId = $merchantId;

        return $this;
    }

    public function getOperatorId(): string
    {
        return $this->operatorId;
    }

    public function setOperatorId(string $operatorId): static
    {
        $this->operatorId = $operatorId;

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

    public function getChanges(): ?array
    {
        return $this->changes;
    }

    public function setChanges(?array $changes): static
    {
        $this->changes = $changes;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * 获取所有可用的操作类型.
     *
     * @return string[]
     */
    public static function getOperations(): array
    {
        return [
            self::OPERATION_CREATE,
            self::OPERATION_UPDATE_PRICE,
            self::OPERATION_UPDATE_COMPARE_PRICE,
            self::OPERATION_UPDATE_ALLOCATION,
            self::OPERATION_UPDATE_REMARK,
            self::OPERATION_ACTIVATE,
            self::OPERATION_PAUSE,
            self::OPERATION_DELETE,
        ];
    }
}
