<?php

namespace App\Entity;

use App\Repository\PlatformRuleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 平台规则 - 平台管理员配置的加价规则、库存优先级规则、结算费率规则.
 */
#[ORM\Entity(repositoryClass: PlatformRuleRepository::class)]
#[ORM\Table(name: 'platform_rules')]
#[ORM\Index(name: 'idx_pr_type', columns: ['type'])]
#[ORM\Index(name: 'idx_pr_category', columns: ['category'])]
#[ORM\Index(name: 'idx_pr_active', columns: ['is_active'])]
#[ORM\HasLifecycleCallbacks]
class PlatformRule
{
    // 规则类型
    public const TYPE_PRICING = 'pricing';                // 加价规则
    public const TYPE_STOCK_PRIORITY = 'stock_priority';  // 库存优先级规则
    public const TYPE_SETTLEMENT_FEE = 'settlement_fee';  // 结算费率规则

    // 规则分类
    public const CATEGORY_MARKUP = 'markup';        // 加价
    public const CATEGORY_DISCOUNT = 'discount';    // 折扣
    public const CATEGORY_PRIORITY = 'priority';    // 优先级
    public const CATEGORY_FEE_RATE = 'fee_rate';    // 费率

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private string $code;

    #[ORM\Column(type: 'string', length: 200)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $type;

    #[ORM\Column(type: 'string', length: 50)]
    private string $category;

    #[ORM\Column(type: 'text')]
    private string $expression;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $conditionExpression = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $priority = 0;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $config = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isSystem = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'string', length: 26, nullable: true)]
    private ?string $createdBy = null;

    /** @var Collection<int, PlatformRuleAssignment> */
    #[ORM\OneToMany(targetEntity: PlatformRuleAssignment::class, mappedBy: 'platformRule', cascade: ['persist', 'remove'])]
    private Collection $assignments;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->assignments = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function setExpression(string $expression): self
    {
        $this->expression = $expression;

        return $this;
    }

    public function getConditionExpression(): ?string
    {
        return $this->conditionExpression;
    }

    public function setConditionExpression(?string $conditionExpression): self
    {
        $this->conditionExpression = $conditionExpression;

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): self
    {
        $this->config = $config;

        return $this;
    }

    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    public function setIsSystem(bool $isSystem): self
    {
        $this->isSystem = $isSystem;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function activate(): self
    {
        $this->isActive = true;

        return $this;
    }

    public function deactivate(): self
    {
        $this->isActive = false;

        return $this;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * @return Collection<int, PlatformRuleAssignment>
     */
    public function getAssignments(): Collection
    {
        return $this->assignments;
    }

    public function addAssignment(PlatformRuleAssignment $assignment): self
    {
        if (!$this->assignments->contains($assignment)) {
            $this->assignments->add($assignment);
            $assignment->setPlatformRule($this);
        }

        return $this;
    }

    public function removeAssignment(PlatformRuleAssignment $assignment): self
    {
        $this->assignments->removeElement($assignment);

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

    // 便捷方法
    public function isPricingRule(): bool
    {
        return $this->type === self::TYPE_PRICING;
    }

    public function isStockPriorityRule(): bool
    {
        return $this->type === self::TYPE_STOCK_PRIORITY;
    }

    public function isSettlementFeeRule(): bool
    {
        return $this->type === self::TYPE_SETTLEMENT_FEE;
    }

    public function canBeDeleted(): bool
    {
        return !$this->isSystem;
    }
}
