<?php

namespace App\Controller;

use App\Dto\Merchant\AssignMerchantRuleRequest;
use App\Dto\Merchant\CreateMerchantRuleRequest;
use App\Dto\Merchant\UpdateMerchantRuleRequest;
use App\Entity\Merchant;
use App\Entity\MerchantRule;
use App\Entity\MerchantRuleAssignment;
use App\Entity\User;
use App\Repository\MerchantRepository;
use App\Repository\MerchantRuleAssignmentRepository;
use App\Repository\MerchantRuleRepository;
use App\Repository\MerchantSalesChannelRepository;
use App\Service\RuleEngine\MerchantRuleService;
use App\Service\RuleEngine\RuleEngineService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/merchant/rules')]
#[IsGranted('ROLE_USER')]
class MerchantRuleController extends AbstractController
{
    /**
     * 获取我的规则列表.
     */
    #[Route('', methods: ['GET'])]
    public function list(
        MerchantRuleRepository $ruleRepository,
        MerchantRepository $merchantRepository,
        #[CurrentUser] User $user,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] int $limit = 20,
        #[MapQueryParameter] ?string $type = null,
        #[MapQueryParameter] ?string $search = null,
    ): JsonResponse {
        $merchant = $this->getMerchant($user, $merchantRepository);
        if (!$merchant) {
            return $this->json(['error' => 'Merchant not found'], Response::HTTP_FORBIDDEN);
        }

        $result = $ruleRepository->findByMerchantPaginated($merchant, $page, $limit, $type, $search);

        return $this->json([
            'data' => array_map(fn (MerchantRule $rule) => $this->formatRule($rule), $result['data']),
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * 验证表达式.
     */
    #[Route('/validate', methods: ['POST'])]
    public function validate(
        Request $request,
        MerchantRuleService $ruleService,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $expression = $data['expression'] ?? '';
        $type = $data['type'] ?? MerchantRule::TYPE_PRICING;

        $result = $ruleService->validateExpression($expression, $type);

        return $this->json($result);
    }

    /**
     * 测试规则执行.
     */
    #[Route('/test', methods: ['POST'])]
    public function test(
        Request $request,
        MerchantRuleService $ruleService,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $expression = $data['expression'] ?? '';
        $conditionExpression = $data['conditionExpression'] ?? null;
        $type = $data['type'] ?? MerchantRule::TYPE_PRICING;
        $testContext = $data['testContext'] ?? [];

        $result = $ruleService->testRule($expression, $conditionExpression, $type, $testContext);

        return $this->json($result);
    }

    /**
     * 获取可用的变量和函数.
     */
    #[Route('/reference', methods: ['GET'])]
    public function reference(
        RuleEngineService $ruleEngine,
        #[MapQueryParameter] string $type = MerchantRule::TYPE_PRICING,
    ): JsonResponse {
        $contextType = $type === MerchantRule::TYPE_PRICING ? 'merchant_pricing' : 'merchant_stock';

        return $this->json([
            'variables' => $ruleEngine->getAvailableVariables($contextType),
            'functions' => $ruleEngine->getAvailableFunctions(),
        ]);
    }

    /**
     * 获取规则详情.
     */
    #[Route('/{id}', methods: ['GET'])]
    public function detail(
        string $id,
        MerchantRuleRepository $ruleRepository,
        MerchantRepository $merchantRepository,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $merchant = $this->getMerchant($user, $merchantRepository);
        if (!$merchant) {
            return $this->json(['error' => 'Merchant not found'], Response::HTTP_FORBIDDEN);
        }

        $rule = $ruleRepository->find($id);
        if (!$rule || $rule->getMerchant()->getId() !== $merchant->getId()) {
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
    public function create(
        #[MapRequestPayload] CreateMerchantRuleRequest $dto,
        MerchantRuleRepository $ruleRepository,
        MerchantRuleService $ruleService,
        MerchantRepository $merchantRepository,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $merchant = $this->getMerchant($user, $merchantRepository);
        if (!$merchant) {
            return $this->json(['error' => 'Merchant not found'], Response::HTTP_FORBIDDEN);
        }

        // 检查编码是否已存在
        if ($ruleRepository->existsByMerchantAndCode($merchant, $dto->code)) {
            return $this->json(['error' => 'Rule code already exists'], Response::HTTP_BAD_REQUEST);
        }

        // 验证表达式
        $validation = $ruleService->validateExpression($dto->expression, $dto->type);
        if (!$validation['valid']) {
            return $this->json(['error' => 'Invalid expression: '.$validation['error']], Response::HTTP_BAD_REQUEST);
        }

        // 验证条件表达式
        if ($dto->conditionExpression) {
            $condValidation = $ruleService->validateExpression($dto->conditionExpression, $dto->type);
            if (!$condValidation['valid']) {
                return $this->json(['error' => 'Invalid condition expression: '.$condValidation['error']], Response::HTTP_BAD_REQUEST);
            }
        }

        $rule = new MerchantRule();
        $rule->setMerchant($merchant);
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

        $ruleRepository->save($rule, true);

        return $this->json([
            'message' => 'Rule created successfully',
            'data' => $this->formatRule($rule),
        ], Response::HTTP_CREATED);
    }

    /**
     * 更新规则.
     */
    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        string $id,
        #[MapRequestPayload] UpdateMerchantRuleRequest $dto,
        MerchantRuleRepository $ruleRepository,
        MerchantRuleService $ruleService,
        EntityManagerInterface $entityManager,
        MerchantRepository $merchantRepository,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $merchant = $this->getMerchant($user, $merchantRepository);
        if (!$merchant) {
            return $this->json(['error' => 'Merchant not found'], Response::HTTP_FORBIDDEN);
        }

        $rule = $ruleRepository->find($id);
        if (!$rule || $rule->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => 'Rule not found'], Response::HTTP_NOT_FOUND);
        }

        // 验证表达式
        if (null !== $dto->expression) {
            $validation = $ruleService->validateExpression($dto->expression, $rule->getType());
            if (!$validation['valid']) {
                return $this->json(['error' => 'Invalid expression: '.$validation['error']], Response::HTTP_BAD_REQUEST);
            }
            $rule->setExpression($dto->expression);
        }

        // 验证条件表达式
        if (null !== $dto->conditionExpression) {
            if ('' !== $dto->conditionExpression) {
                $condValidation = $ruleService->validateExpression($dto->conditionExpression, $rule->getType());
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

        $entityManager->flush();

        return $this->json([
            'message' => 'Rule updated successfully',
            'data' => $this->formatRule($rule),
        ]);
    }

    /**
     * 删除规则.
     */
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(
        string $id,
        MerchantRuleRepository $ruleRepository,
        MerchantRepository $merchantRepository,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $merchant = $this->getMerchant($user, $merchantRepository);
        if (!$merchant) {
            return $this->json(['error' => 'Merchant not found'], Response::HTTP_FORBIDDEN);
        }

        $rule = $ruleRepository->find($id);
        if (!$rule || $rule->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => 'Rule not found'], Response::HTTP_NOT_FOUND);
        }

        $ruleRepository->remove($rule, true);

        return $this->json(['message' => 'Rule deleted successfully']);
    }

    /**
     * 获取规则的分配列表.
     */
    #[Route('/{id}/assignments', methods: ['GET'])]
    public function listAssignments(
        string $id,
        MerchantRuleRepository $ruleRepository,
        MerchantRuleAssignmentRepository $assignmentRepository,
        MerchantRepository $merchantRepository,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $merchant = $this->getMerchant($user, $merchantRepository);
        if (!$merchant) {
            return $this->json(['error' => 'Merchant not found'], Response::HTTP_FORBIDDEN);
        }

        $rule = $ruleRepository->find($id);
        if (!$rule || $rule->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => 'Rule not found'], Response::HTTP_NOT_FOUND);
        }

        $assignments = $assignmentRepository->findByRule($rule);

        return $this->json([
            'data' => array_map(fn (MerchantRuleAssignment $a) => $this->formatAssignment($a), $assignments),
        ]);
    }

    /**
     * 分配规则到渠道.
     */
    #[Route('/{id}/assignments', methods: ['POST'])]
    public function assign(
        string $id,
        #[MapRequestPayload] AssignMerchantRuleRequest $dto,
        MerchantRuleRepository $ruleRepository,
        MerchantRuleAssignmentRepository $assignmentRepository,
        MerchantSalesChannelRepository $merchantChannelRepository,
        MerchantRepository $merchantRepository,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $merchant = $this->getMerchant($user, $merchantRepository);
        if (!$merchant) {
            return $this->json(['error' => 'Merchant not found'], Response::HTTP_FORBIDDEN);
        }

        $rule = $ruleRepository->find($id);
        if (!$rule || $rule->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => 'Rule not found'], Response::HTTP_NOT_FOUND);
        }

        $merchantChannel = $merchantChannelRepository->find($dto->merchantSalesChannelId);
        if (!$merchantChannel || $merchantChannel->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => 'Merchant sales channel not found'], Response::HTTP_NOT_FOUND);
        }

        // 检查是否已分配
        if ($assignmentRepository->existsByRuleAndChannel($rule, $merchantChannel)) {
            return $this->json(['error' => 'Rule already assigned to this channel'], Response::HTTP_BAD_REQUEST);
        }

        $assignment = new MerchantRuleAssignment();
        $assignment->setMerchantRule($rule);
        $assignment->setMerchantSalesChannel($merchantChannel);
        $assignment->setPriorityOverride($dto->priorityOverride);
        $assignment->setConfigOverride($dto->configOverride);
        $assignment->setIsActive($dto->isActive);

        $assignmentRepository->save($assignment, true);

        return $this->json([
            'message' => 'Rule assigned successfully',
            'data' => $this->formatAssignment($assignment),
        ], Response::HTTP_CREATED);
    }

    /**
     * 取消规则分配.
     */
    #[Route('/assignments/{assignmentId}', methods: ['DELETE'])]
    public function unassign(
        string $assignmentId,
        MerchantRuleAssignmentRepository $assignmentRepository,
        MerchantRepository $merchantRepository,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $merchant = $this->getMerchant($user, $merchantRepository);
        if (!$merchant) {
            return $this->json(['error' => 'Merchant not found'], Response::HTTP_FORBIDDEN);
        }

        $assignment = $assignmentRepository->find($assignmentId);
        if (!$assignment) {
            return $this->json(['error' => 'Assignment not found'], Response::HTTP_NOT_FOUND);
        }

        // 验证归属
        if ($assignment->getMerchantRule()->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => 'Assignment not found'], Response::HTTP_NOT_FOUND);
        }

        $assignmentRepository->remove($assignment, true);

        return $this->json(['message' => 'Assignment removed successfully']);
    }

    /**
     * 切换分配状态.
     */
    #[Route('/assignments/{assignmentId}/toggle', methods: ['PUT'])]
    public function toggleAssignment(
        string $assignmentId,
        MerchantRuleAssignmentRepository $assignmentRepository,
        EntityManagerInterface $entityManager,
        MerchantRepository $merchantRepository,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $merchant = $this->getMerchant($user, $merchantRepository);
        if (!$merchant) {
            return $this->json(['error' => 'Merchant not found'], Response::HTTP_FORBIDDEN);
        }

        $assignment = $assignmentRepository->find($assignmentId);
        if (!$assignment) {
            return $this->json(['error' => 'Assignment not found'], Response::HTTP_NOT_FOUND);
        }

        // 验证归属
        if ($assignment->getMerchantRule()->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => 'Assignment not found'], Response::HTTP_NOT_FOUND);
        }

        $assignment->setIsActive(!$assignment->isActive());
        $entityManager->flush();

        return $this->json([
            'message' => $assignment->isActive() ? 'Assignment enabled' : 'Assignment disabled',
            'data' => $this->formatAssignment($assignment),
        ]);
    }

    /**
     * 获取当前商户.
     */
    private function getMerchant(User $user, MerchantRepository $merchantRepository): ?Merchant
    {
        return $merchantRepository->findByUser($user);
    }

    private function formatRule(MerchantRule $rule, bool $includeAssignments = false): array
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
            'isActive' => $rule->isActive(),
            'createdAt' => $rule->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $rule->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];

        if ($includeAssignments) {
            $data['assignments'] = array_map(
                fn (MerchantRuleAssignment $a) => $this->formatAssignment($a),
                $rule->getAssignments()->toArray()
            );
        }

        return $data;
    }

    private function formatAssignment(MerchantRuleAssignment $assignment): array
    {
        $channel = $assignment->getMerchantSalesChannel();
        $salesChannel = $channel->getSalesChannel();

        return [
            'id' => $assignment->getId(),
            'merchantSalesChannel' => [
                'id' => $channel->getId(),
                'salesChannel' => [
                    'id' => $salesChannel->getId(),
                    'code' => $salesChannel->getCode(),
                    'name' => $salesChannel->getName(),
                ],
            ],
            'priorityOverride' => $assignment->getPriorityOverride(),
            'configOverride' => $assignment->getConfigOverride(),
            'effectivePriority' => $assignment->getEffectivePriority(),
            'isActive' => $assignment->isActive(),
            'createdAt' => $assignment->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
