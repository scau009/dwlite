<?php

namespace App\Controller\Admin;

use App\Attribute\AdminOnly;
use App\Dto\Admin\AssignPlatformRuleRequest;
use App\Dto\Admin\CreatePlatformRuleRequest;
use App\Dto\Admin\UpdatePlatformRuleRequest;
use App\Entity\PlatformRule;
use App\Entity\PlatformRuleAssignment;
use App\Repository\ChannelProductRepository;
use App\Repository\MerchantRepository;
use App\Repository\PlatformRuleAssignmentRepository;
use App\Repository\PlatformRuleRepository;
use App\Service\RuleEngine\PlatformRuleService;
use App\Service\RuleEngine\RuleEngineService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/platform-rules')]
#[AdminOnly]
class PlatformRuleController extends AbstractController
{
    public function __construct(
        private readonly PlatformRuleRepository $ruleRepository,
        private readonly PlatformRuleAssignmentRepository $assignmentRepository,
        private readonly MerchantRepository $merchantRepository,
        private readonly ChannelProductRepository $channelProductRepository,
        private readonly PlatformRuleService $ruleService,
        private readonly RuleEngineService $ruleEngine,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * 获取规则列表.
     */
    #[Route('', methods: ['GET'])]
    public function list(
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] int $limit = 20,
        #[MapQueryParameter] ?string $type = null,
        #[MapQueryParameter] ?string $search = null,
    ): JsonResponse {
        $result = $this->ruleRepository->findPaginated($page, $limit, $type, $search);

        return $this->json([
            'data' => array_map(fn (PlatformRule $rule) => $this->formatRule($rule), $result['data']),
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * 获取规则详情.
     */
    #[Route('/{id}', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        $rule = $this->ruleRepository->find($id);
        if (!$rule) {
            return $this->json(['error' => 'Rule not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'data' => $this->formatRule($rule, true),
        ]);
    }

    /**
     * 创建规则.
     */
    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreatePlatformRuleRequest $dto): JsonResponse
    {
        // 检查编码是否已存在
        if ($this->ruleRepository->existsByCode($dto->code)) {
            return $this->json(['error' => 'Rule code already exists'], Response::HTTP_BAD_REQUEST);
        }

        // 验证表达式
        $validation = $this->ruleService->validateExpression($dto->expression, $dto->type);
        if (!$validation['valid']) {
            return $this->json(['error' => 'Invalid expression: '.$validation['error']], Response::HTTP_BAD_REQUEST);
        }

        // 验证条件表达式
        if ($dto->conditionExpression) {
            $condValidation = $this->ruleService->validateExpression($dto->conditionExpression, $dto->type);
            if (!$condValidation['valid']) {
                return $this->json(['error' => 'Invalid condition expression: '.$condValidation['error']], Response::HTTP_BAD_REQUEST);
            }
        }

        $rule = new PlatformRule();
        $rule->setCode($dto->code);
        $rule->setName($dto->name);
        $rule->setDescription($dto->description);
        $rule->setType($dto->type);
        $rule->setCategory($dto->category);
        $rule->setExpression($dto->expression);
        $rule->setConditionExpression($dto->conditionExpression);
        $rule->setPriority($dto->priority);
        $rule->setConfig($dto->config);
        $rule->setIsActive($dto->isActive);
        $rule->setCreatedBy($this->getUser()->getId());

        $this->ruleRepository->save($rule, true);

        return $this->json([
            'message' => 'Rule created successfully',
            'data' => $this->formatRule($rule),
        ], Response::HTTP_CREATED);
    }

    /**
     * 更新规则.
     */
    #[Route('/{id}', methods: ['PUT'])]
    public function update(string $id, #[MapRequestPayload] UpdatePlatformRuleRequest $dto): JsonResponse
    {
        $rule = $this->ruleRepository->find($id);
        if (!$rule) {
            return $this->json(['error' => 'Rule not found'], Response::HTTP_NOT_FOUND);
        }

        // 验证表达式
        if (null !== $dto->expression) {
            $validation = $this->ruleService->validateExpression($dto->expression, $rule->getType());
            if (!$validation['valid']) {
                return $this->json(['error' => 'Invalid expression: '.$validation['error']], Response::HTTP_BAD_REQUEST);
            }
            $rule->setExpression($dto->expression);
        }

        // 验证条件表达式
        if (null !== $dto->conditionExpression) {
            if ('' !== $dto->conditionExpression) {
                $condValidation = $this->ruleService->validateExpression($dto->conditionExpression, $rule->getType());
                if (!$condValidation['valid']) {
                    return $this->json(['error' => 'Invalid condition expression: '.$condValidation['error']], Response::HTTP_BAD_REQUEST);
                }
            }
            $rule->setConditionExpression($dto->conditionExpression ?: null);
        }

        if (null !== $dto->name) {
            $rule->setName($dto->name);
        }
        if (null !== $dto->description) {
            $rule->setDescription($dto->description);
        }
        if (null !== $dto->category) {
            $rule->setCategory($dto->category);
        }
        if (null !== $dto->priority) {
            $rule->setPriority($dto->priority);
        }
        if (null !== $dto->config) {
            $rule->setConfig($dto->config);
        }
        if (null !== $dto->isActive) {
            $rule->setIsActive($dto->isActive);
        }

        $this->entityManager->flush();

        return $this->json([
            'message' => 'Rule updated successfully',
            'data' => $this->formatRule($rule),
        ]);
    }

    /**
     * 删除规则.
     */
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $rule = $this->ruleRepository->find($id);
        if (!$rule) {
            return $this->json(['error' => 'Rule not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$rule->canBeDeleted()) {
            return $this->json(['error' => 'System rules cannot be deleted'], Response::HTTP_FORBIDDEN);
        }

        $this->ruleRepository->remove($rule, true);

        return $this->json(['message' => 'Rule deleted successfully']);
    }

    /**
     * 验证表达式.
     */
    #[Route('/validate', methods: ['POST'])]
    public function validate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $expression = $data['expression'] ?? '';
        $type = $data['type'] ?? PlatformRule::TYPE_PRICING;

        $result = $this->ruleService->validateExpression($expression, $type);

        return $this->json($result);
    }

    /**
     * 测试规则执行.
     */
    #[Route('/test', methods: ['POST'])]
    public function test(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $expression = $data['expression'] ?? '';
        $conditionExpression = $data['conditionExpression'] ?? null;
        $type = $data['type'] ?? PlatformRule::TYPE_PRICING;
        $testContext = $data['testContext'] ?? [];

        $result = $this->ruleService->testRule($expression, $conditionExpression, $type, $testContext);

        return $this->json($result);
    }

    /**
     * 获取可用的变量和函数.
     */
    #[Route('/reference', methods: ['GET'])]
    public function reference(
        #[MapQueryParameter] string $type = PlatformRule::TYPE_PRICING,
    ): JsonResponse {
        $contextType = match ($type) {
            PlatformRule::TYPE_PRICING => 'platform_pricing',
            PlatformRule::TYPE_SETTLEMENT_FEE => 'platform_settlement',
            default => 'platform_pricing',
        };

        return $this->json([
            'variables' => $this->ruleEngine->getAvailableVariables($contextType),
            'functions' => $this->ruleEngine->getAvailableFunctions(),
        ]);
    }

    /**
     * 获取规则的分配列表.
     */
    #[Route('/{id}/assignments', methods: ['GET'])]
    public function listAssignments(string $id): JsonResponse
    {
        $rule = $this->ruleRepository->find($id);
        if (!$rule) {
            return $this->json(['error' => 'Rule not found'], Response::HTTP_NOT_FOUND);
        }

        $assignments = $this->assignmentRepository->findByRule($rule);

        return $this->json([
            'data' => array_map(fn (PlatformRuleAssignment $a) => $this->formatAssignment($a), $assignments),
        ]);
    }

    /**
     * 获取范围的规则分配列表.
     */
    #[Route('/assignments/scope/{scopeType}/{scopeId}', methods: ['GET'])]
    public function listByScope(string $scopeType, string $scopeId): JsonResponse
    {
        $assignments = $this->assignmentRepository->findByScope($scopeType, $scopeId);

        return $this->json([
            'data' => array_map(fn (PlatformRuleAssignment $a) => $this->formatAssignmentWithRule($a), $assignments),
        ]);
    }

    /**
     * 分配规则.
     */
    #[Route('/{id}/assignments', methods: ['POST'])]
    public function assign(string $id, #[MapRequestPayload] AssignPlatformRuleRequest $dto): JsonResponse
    {
        $rule = $this->ruleRepository->find($id);
        if (!$rule) {
            return $this->json(['error' => 'Rule not found'], Response::HTTP_NOT_FOUND);
        }

        // 验证范围实体是否存在
        if ($dto->scopeType === PlatformRuleAssignment::SCOPE_MERCHANT) {
            $entity = $this->merchantRepository->find($dto->scopeId);
            if (!$entity) {
                return $this->json(['error' => 'Merchant not found'], Response::HTTP_NOT_FOUND);
            }
        } else {
            $entity = $this->channelProductRepository->find($dto->scopeId);
            if (!$entity) {
                return $this->json(['error' => 'Channel product not found'], Response::HTTP_NOT_FOUND);
            }
        }

        // 检查是否已分配
        if ($this->assignmentRepository->existsByRuleAndScope($rule, $dto->scopeType, $dto->scopeId)) {
            return $this->json(['error' => 'Rule already assigned to this scope'], Response::HTTP_BAD_REQUEST);
        }

        $assignment = new PlatformRuleAssignment();
        $assignment->setPlatformRule($rule);
        $assignment->setScopeType($dto->scopeType);
        $assignment->setScopeId($dto->scopeId);
        $assignment->setPriorityOverride($dto->priorityOverride);
        $assignment->setConfigOverride($dto->configOverride);
        $assignment->setIsActive($dto->isActive);

        $this->assignmentRepository->save($assignment, true);

        return $this->json([
            'message' => 'Rule assigned successfully',
            'data' => $this->formatAssignment($assignment),
        ], Response::HTTP_CREATED);
    }

    /**
     * 取消规则分配.
     */
    #[Route('/assignments/{assignmentId}', methods: ['DELETE'])]
    public function unassign(string $assignmentId): JsonResponse
    {
        $assignment = $this->assignmentRepository->find($assignmentId);
        if (!$assignment) {
            return $this->json(['error' => 'Assignment not found'], Response::HTTP_NOT_FOUND);
        }

        $this->assignmentRepository->remove($assignment, true);

        return $this->json(['message' => 'Assignment removed successfully']);
    }

    /**
     * 切换分配状态.
     */
    #[Route('/assignments/{assignmentId}/toggle', methods: ['PUT'])]
    public function toggleAssignment(string $assignmentId): JsonResponse
    {
        $assignment = $this->assignmentRepository->find($assignmentId);
        if (!$assignment) {
            return $this->json(['error' => 'Assignment not found'], Response::HTTP_NOT_FOUND);
        }

        $assignment->setIsActive(!$assignment->isActive());
        $this->entityManager->flush();

        return $this->json([
            'message' => $assignment->isActive() ? 'Assignment enabled' : 'Assignment disabled',
            'data' => $this->formatAssignment($assignment),
        ]);
    }

    private function formatRule(PlatformRule $rule, bool $includeAssignments = false): array
    {
        $data = [
            'id' => $rule->getId(),
            'code' => $rule->getCode(),
            'name' => $rule->getName(),
            'description' => $rule->getDescription(),
            'type' => $rule->getType(),
            'category' => $rule->getCategory(),
            'expression' => $rule->getExpression(),
            'conditionExpression' => $rule->getConditionExpression(),
            'priority' => $rule->getPriority(),
            'config' => $rule->getConfig(),
            'isSystem' => $rule->isSystem(),
            'isActive' => $rule->isActive(),
            'createdBy' => $rule->getCreatedBy(),
            'createdAt' => $rule->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $rule->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];

        if ($includeAssignments) {
            $data['assignments'] = array_map(
                fn (PlatformRuleAssignment $a) => $this->formatAssignment($a),
                $rule->getAssignments()->toArray()
            );
        }

        return $data;
    }

    private function formatAssignment(PlatformRuleAssignment $assignment): array
    {
        return [
            'id' => $assignment->getId(),
            'scopeType' => $assignment->getScopeType(),
            'scopeId' => $assignment->getScopeId(),
            'scopeName' => $this->getScopeName($assignment),
            'priorityOverride' => $assignment->getPriorityOverride(),
            'configOverride' => $assignment->getConfigOverride(),
            'effectivePriority' => $assignment->getEffectivePriority(),
            'isActive' => $assignment->isActive(),
            'createdAt' => $assignment->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function formatAssignmentWithRule(PlatformRuleAssignment $assignment): array
    {
        $rule = $assignment->getPlatformRule();

        return [
            'id' => $assignment->getId(),
            'rule' => [
                'id' => $rule->getId(),
                'code' => $rule->getCode(),
                'name' => $rule->getName(),
                'type' => $rule->getType(),
                'category' => $rule->getCategory(),
            ],
            'priorityOverride' => $assignment->getPriorityOverride(),
            'configOverride' => $assignment->getConfigOverride(),
            'effectivePriority' => $assignment->getEffectivePriority(),
            'isActive' => $assignment->isActive(),
            'createdAt' => $assignment->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function getScopeName(PlatformRuleAssignment $assignment): string
    {
        try {
            if ($assignment->getScopeType() === PlatformRuleAssignment::SCOPE_MERCHANT) {
                $merchant = $this->merchantRepository->find($assignment->getScopeId());

                return $merchant ? $merchant->getCompanyName() : 'Unknown Merchant';
            }

            $product = $this->channelProductRepository->find($assignment->getScopeId());

            return $product ? $product->getProductSku()->getProduct()->getName() : 'Unknown Product';
        } catch (\Throwable) {
            return 'Unknown';
        }
    }
}
