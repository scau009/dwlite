<?php

namespace App\Entity;

use App\Repository\RuleExecutionLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * 规则执行日志 - 记录规则执行的输入、输出和性能数据.
 */
#[ORM\Entity(repositoryClass: RuleExecutionLogRepository::class)]
#[ORM\Table(name: 'rule_execution_logs')]
#[ORM\Index(name: 'idx_rel_rule', columns: ['rule_type', 'rule_id'])]
#[ORM\Index(name: 'idx_rel_context', columns: ['context_type', 'context_id'])]
#[ORM\Index(name: 'idx_rel_created', columns: ['created_at'])]
#[ORM\Index(name: 'idx_rel_success', columns: ['success'])]
class RuleExecutionLog
{
    // 规则类型
    public const RULE_TYPE_MERCHANT = 'merchant';
    public const RULE_TYPE_PLATFORM = 'platform';

    // 执行上下文类型
    public const CONTEXT_PRICING = 'pricing';
    public const CONTEXT_STOCK_ALLOCATION = 'stock_allocation';
    public const CONTEXT_SETTLEMENT = 'settlement';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(type: 'string', length: 20)]
    private string $ruleType;

    #[ORM\Column(type: 'string', length: 26)]
    private string $ruleId;

    #[ORM\Column(type: 'string', length: 50)]
    private string $contextType;

    #[ORM\Column(type: 'string', length: 26, nullable: true)]
    private ?string $contextId = null;

    #[ORM\Column(type: 'json')]
    private array $inputData;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $outputValue = null;

    #[ORM\Column(type: 'integer')]
    private int $executionTimeMs;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $success = true;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

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

    public function getRuleType(): string
    {
        return $this->ruleType;
    }

    public function setRuleType(string $ruleType): self
    {
        $this->ruleType = $ruleType;

        return $this;
    }

    public function getRuleId(): string
    {
        return $this->ruleId;
    }

    public function setRuleId(string $ruleId): self
    {
        $this->ruleId = $ruleId;

        return $this;
    }

    public function getContextType(): string
    {
        return $this->contextType;
    }

    public function setContextType(string $contextType): self
    {
        $this->contextType = $contextType;

        return $this;
    }

    public function getContextId(): ?string
    {
        return $this->contextId;
    }

    public function setContextId(?string $contextId): self
    {
        $this->contextId = $contextId;

        return $this;
    }

    public function getInputData(): array
    {
        return $this->inputData;
    }

    public function setInputData(array $inputData): self
    {
        $this->inputData = $inputData;

        return $this;
    }

    public function getOutputValue(): ?string
    {
        return $this->outputValue;
    }

    public function setOutputValue(?string $outputValue): self
    {
        $this->outputValue = $outputValue;

        return $this;
    }

    public function getExecutionTimeMs(): int
    {
        return $this->executionTimeMs;
    }

    public function setExecutionTimeMs(int $executionTimeMs): self
    {
        $this->executionTimeMs = $executionTimeMs;

        return $this;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): self
    {
        $this->success = $success;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    // 静态工厂方法
    public static function createSuccess(
        string $ruleType,
        string $ruleId,
        string $contextType,
        ?string $contextId,
        array $inputData,
        string $outputValue,
        int $executionTimeMs,
    ): self {
        $log = new self();
        $log->ruleType = $ruleType;
        $log->ruleId = $ruleId;
        $log->contextType = $contextType;
        $log->contextId = $contextId;
        $log->inputData = $inputData;
        $log->outputValue = $outputValue;
        $log->executionTimeMs = $executionTimeMs;
        $log->success = true;

        return $log;
    }

    public static function createFailure(
        string $ruleType,
        string $ruleId,
        string $contextType,
        ?string $contextId,
        array $inputData,
        int $executionTimeMs,
        string $errorMessage,
    ): self {
        $log = new self();
        $log->ruleType = $ruleType;
        $log->ruleId = $ruleId;
        $log->contextType = $contextType;
        $log->contextId = $contextId;
        $log->inputData = $inputData;
        $log->executionTimeMs = $executionTimeMs;
        $log->success = false;
        $log->errorMessage = $errorMessage;

        return $log;
    }
}
