<?php

namespace App\Entity;

use App\Repository\PlatformRuleAssignmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 平台规则分配 - 将平台规则分配到商户或渠道商品.
 */
#[ORM\Entity(repositoryClass: PlatformRuleAssignmentRepository::class)]
#[ORM\Table(name: 'platform_rule_assignments')]
#[ORM\UniqueConstraint(name: 'uniq_pra', columns: ['platform_rule_id', 'scope_type', 'scope_id'])]
#[ORM\Index(name: 'idx_pra_rule', columns: ['platform_rule_id'])]
#[ORM\Index(name: 'idx_pra_scope', columns: ['scope_type', 'scope_id'])]
#[ORM\HasLifecycleCallbacks]
class PlatformRuleAssignment
{
    // 范围类型
    public const SCOPE_MERCHANT = 'merchant';                // 商户级别
    public const SCOPE_CHANNEL_PRODUCT = 'channel_product';  // 渠道商品级别

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: PlatformRule::class, inversedBy: 'assignments')]
    #[ORM\JoinColumn(name: 'platform_rule_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private PlatformRule $platformRule;

    #[ORM\Column(type: 'string', length: 50)]
    private string $scopeType;

    #[ORM\Column(type: 'string', length: 26)]
    private string $scopeId;

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

    public function getPlatformRule(): PlatformRule
    {
        return $this->platformRule;
    }

    public function setPlatformRule(PlatformRule $platformRule): self
    {
        $this->platformRule = $platformRule;

        return $this;
    }

    public function getScopeType(): string
    {
        return $this->scopeType;
    }

    public function setScopeType(string $scopeType): self
    {
        $this->scopeType = $scopeType;

        return $this;
    }

    public function getScopeId(): string
    {
        return $this->scopeId;
    }

    public function setScopeId(string $scopeId): self
    {
        $this->scopeId = $scopeId;

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
        return $this->priorityOverride ?? $this->platformRule->getPriority();
    }

    /**
     * 获取合并后的配置（规则配置 + 覆盖配置）.
     */
    public function getMergedConfig(): array
    {
        $baseConfig = $this->platformRule->getConfig() ?? [];
        $overrideConfig = $this->configOverride ?? [];

        return array_merge($baseConfig, $overrideConfig);
    }

    // 便捷方法
    public function isMerchantScope(): bool
    {
        return $this->scopeType === self::SCOPE_MERCHANT;
    }

    public function isChannelProductScope(): bool
    {
        return $this->scopeType === self::SCOPE_CHANNEL_PRODUCT;
    }
}
