<?php

namespace App\Entity;

use App\Repository\MerchantRuleAssignmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 商户规则分配 - 将商户规则分配到具体的渠道配置.
 */
#[ORM\Entity(repositoryClass: MerchantRuleAssignmentRepository::class)]
#[ORM\Table(name: 'merchant_rule_assignments')]
#[ORM\UniqueConstraint(name: 'uniq_mra', columns: ['merchant_rule_id', 'merchant_sales_channel_id'])]
#[ORM\Index(name: 'idx_mra_rule', columns: ['merchant_rule_id'])]
#[ORM\Index(name: 'idx_mra_channel', columns: ['merchant_sales_channel_id'])]
#[ORM\HasLifecycleCallbacks]
class MerchantRuleAssignment
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: MerchantRule::class, inversedBy: 'assignments')]
    #[ORM\JoinColumn(name: 'merchant_rule_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MerchantRule $merchantRule;

    #[ORM\ManyToOne(targetEntity: MerchantSalesChannel::class)]
    #[ORM\JoinColumn(name: 'merchant_sales_channel_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MerchantSalesChannel $merchantSalesChannel;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $priorityOverride = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $configOverride = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = (string) new Ulid();
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

    public function getMerchantRule(): MerchantRule
    {
        return $this->merchantRule;
    }

    public function setMerchantRule(MerchantRule $merchantRule): self
    {
        $this->merchantRule = $merchantRule;

        return $this;
    }

    public function getMerchantSalesChannel(): MerchantSalesChannel
    {
        return $this->merchantSalesChannel;
    }

    public function setMerchantSalesChannel(MerchantSalesChannel $merchantSalesChannel): self
    {
        $this->merchantSalesChannel = $merchantSalesChannel;

        return $this;
    }

    public function getPriorityOverride(): ?int
    {
        return $this->priorityOverride;
    }

    public function setPriorityOverride(?int $priorityOverride): self
    {
        $this->priorityOverride = $priorityOverride;

        return $this;
    }

    public function getConfigOverride(): ?array
    {
        return $this->configOverride;
    }

    public function setConfigOverride(?array $configOverride): self
    {
        $this->configOverride = $configOverride;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * 获取有效的优先级（覆盖值或规则默认值）.
     */
    public function getEffectivePriority(): int
    {
        return $this->priorityOverride ?? $this->merchantRule->getPriority();
    }

    /**
     * 获取合并后的配置（规则配置 + 覆盖配置）.
     */
    public function getMergedConfig(): array
    {
        $baseConfig = $this->merchantRule->getConfig() ?? [];
        $overrideConfig = $this->configOverride ?? [];

        return array_merge($baseConfig, $overrideConfig);
    }
}
