<?php

namespace App\Entity;

use App\Repository\MerchantRuleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 商户规则 - 商户自己配置的定价规则和库存分配规则.
 */
#[ORM\Entity(repositoryClass: MerchantRuleRepository::class)]
#[ORM\Table(name: 'merchant_rules')]
#[ORM\UniqueConstraint(name: 'uniq_merchant_rule_code', columns: ['merchant_id', 'code'])]
#[ORM\Index(name: 'idx_mr_merchant', columns: ['merchant_id'])]
#[ORM\Index(name: 'idx_mr_type', columns: ['type'])]
#[ORM\Index(name: 'idx_mr_active', columns: ['is_active'])]
#[ORM\HasLifecycleCallbacks]
class MerchantRule
{
    // 规则类型
    public const TYPE_PRICING = 'pricing';                    // 定价规则
    public const TYPE_STOCK_ALLOCATION = 'stock_allocation';  // 库存分配规则

    // 规则分类
    public const CATEGORY_MARKUP = 'markup';      // 加价
    public const CATEGORY_DISCOUNT = 'discount';  // 折扣
    public const CATEGORY_RATIO = 'ratio';        // 比例
    public const CATEGORY_LIMIT = 'limit';        // 上限

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Merchant::class)]
    #[ORM\JoinColumn(name: 'merchant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Merchant $merchant;

    #[ORM\Column(type: 'string', length: 100)]
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

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    /** @var Collection<int, MerchantRuleAssignment> */
    #[ORM\OneToMany(targetEntity: MerchantRuleAssignment::class, mappedBy: 'merchantRule', cascade: ['persist', 'remove'])]
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

    public function getMerchant(): Merchant
    {
        return $this->merchant;
    }

    public function setMerchant(Merchant $merchant): self
    {
        $this->merchant = $merchant;

        return $this;
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

    /**
     * @return Collection<int, MerchantRuleAssignment>
     */
    public function getAssignments(): Collection
    {
        return $this->assignments;
    }

    public function addAssignment(MerchantRuleAssignment $assignment): self
    {
        if (!$this->assignments->contains($assignment)) {
            $this->assignments->add($assignment);
            $assignment->setMerchantRule($this);
        }

        return $this;
    }

    public function removeAssignment(MerchantRuleAssignment $assignment): self
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

    public function isStockAllocationRule(): bool
    {
        return $this->type === self::TYPE_STOCK_ALLOCATION;
    }
}
