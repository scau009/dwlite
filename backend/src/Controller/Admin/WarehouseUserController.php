<?php

namespace App\Controller\Admin;

use App\Attribute\AdminOnly;
use App\Dto\Admin\CreateWarehouseUserRequest;
use App\Dto\Admin\UpdateWarehouseUserRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\WarehouseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 管理员 - 仓库用户管理.
 */
#[Route('/api/admin/warehouse-users')]
#[IsGranted('ROLE_USER')]
#[AdminOnly]
class WarehouseUserController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private WarehouseRepository $warehouseRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * 获取仓库用户列表.
     */
    #[Route('', name: 'admin_warehouse_users_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', '1'));
        $limit = min(50, max(1, (int) $request->query->get('limit', '20')));
        $warehouseId = $request->query->get('warehouseId');

        $result = $this->findWarehouseUsersPaginated($page, $limit, $warehouseId);

        return $this->json([
            'data' => array_map([$this, 'serializeUser'], $result['data']),
            'meta' => $result['meta'],
        ]);
    }

    /**
     * 获取仓库用户详情.
     */
    #[Route('/{id}', name: 'admin_warehouse_users_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if ($user === null || !$user->isWarehouse()) {
            return $this->json(['error' => 'Warehouse user not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'data' => $this->serializeUser($user),
        ]);
    }

    /**
     * 创建仓库用户.
     */
    #[Route('', name: 'admin_warehouse_users_create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateWarehouseUserRequest $dto
    ): JsonResponse {
        // 检查邮箱是否已存在
        $existingUser = $this->userRepository->findOneBy(['email' => $dto->email]);
        if ($existingUser !== null) {
            return $this->json(['error' => 'Email already exists'], Response::HTTP_BAD_REQUEST);
        }

        // 检查仓库是否存在
        $warehouse = $this->warehouseRepository->find($dto->warehouseId);
        if ($warehouse === null) {
            return $this->json(['error' => 'Warehouse not found'], Response::HTTP_BAD_REQUEST);
        }

        // 创建用户
        $user = new User();
        $user->setEmail($dto->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));
        $user->setAccountType(User::ACCOUNT_TYPE_WAREHOUSE);
        $user->setWarehouse($warehouse);
        $user->setIsVerified(true); // 管理员创建的用户默认已验证

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json([
            'message' => $this->translator->trans('admin.warehouseUser.created'),
            'data' => $this->serializeUser($user),
        ], Response::HTTP_CREATED);
    }

    /**
     * 更新仓库用户.
     */
    #[Route('/{id}', name: 'admin_warehouse_users_update', methods: ['PUT'])]
    public function update(
        string $id,
        #[MapRequestPayload] UpdateWarehouseUserRequest $dto
    ): JsonResponse {
        $user = $this->userRepository->find($id);

        if ($user === null || !$user->isWarehouse()) {
            return $this->json(['error' => 'Warehouse user not found'], Response::HTTP_NOT_FOUND);
        }

        // 更新邮箱
        if ($dto->email !== null && $dto->email !== $user->getEmail()) {
            $existingUser = $this->userRepository->findOneBy(['email' => $dto->email]);
            if ($existingUser !== null) {
                return $this->json(['error' => 'Email already exists'], Response::HTTP_BAD_REQUEST);
            }
            $user->setEmail($dto->email);
        }

        // 更新密码
        if ($dto->password !== null) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));
        }

        // 更新仓库
        if ($dto->warehouseId !== null) {
            $warehouse = $this->warehouseRepository->find($dto->warehouseId);
            if ($warehouse === null) {
                return $this->json(['error' => 'Warehouse not found'], Response::HTTP_BAD_REQUEST);
            }
            $user->setWarehouse($warehouse);
        }

        $this->entityManager->flush();

        return $this->json([
            'message' => $this->translator->trans('admin.warehouseUser.updated'),
            'data' => $this->serializeUser($user),
        ]);
    }

    /**
     * 删除仓库用户.
     */
    #[Route('/{id}', name: 'admin_warehouse_users_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if ($user === null || !$user->isWarehouse()) {
            return $this->json(['error' => 'Warehouse user not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return $this->json([
            'message' => $this->translator->trans('admin.warehouseUser.deleted'),
        ]);
    }

    /**
     * 分页查询仓库用户.
     */
    private function findWarehouseUsersPaginated(int $page, int $limit, ?string $warehouseId): array
    {
        $qb = $this->userRepository->createQueryBuilder('u')
            ->andWhere('u.accountType = :accountType')
            ->setParameter('accountType', User::ACCOUNT_TYPE_WAREHOUSE)
            ->orderBy('u.createdAt', 'DESC');

        if ($warehouseId !== null) {
            $qb->leftJoin('u.warehouse', 'w')
                ->andWhere('w.id = :warehouseId')
                ->setParameter('warehouseId', $warehouseId);
        }

        // 计算总数
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // 分页
        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $data = $qb->getQuery()->getResult();

        return [
            'data' => $data,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => (int) ceil($total / $limit),
            ],
        ];
    }

    /**
     * 序列化用户.
     */
    private function serializeUser(User $user): array
    {
        $warehouse = $user->getWarehouse();

        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'accountType' => $user->getAccountType(),
            'isVerified' => $user->isVerified(),
            'warehouse' => $warehouse ? [
                'id' => $warehouse->getId(),
                'code' => $warehouse->getCode(),
                'name' => $warehouse->getName(),
            ] : null,
            'createdAt' => $user->getCreatedAt()->format('c'),
            'updatedAt' => $user->getUpdatedAt()->format('c'),
        ];
    }
}
