<?php

namespace App\Controller;

use App\Dto\Merchant\CreateMerchantWarehouseRequest;
use App\Dto\Merchant\UpdateMerchantWarehouseRequest;
use App\Entity\Merchant;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Repository\MerchantRepository;
use App\Repository\WarehouseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/merchant/warehouses')]
#[IsGranted('ROLE_USER')]
class MerchantWarehouseController extends AbstractController
{
    /**
     * 获取商户的仓库列表.
     */
    #[Route('', methods: ['GET'])]
    public function list(
        WarehouseRepository $warehouseRepository,
        MerchantRepository $merchantRepository,
        #[CurrentUser] User $user,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] int $limit = 20,
        #[MapQueryParameter] ?string $name = null,
        #[MapQueryParameter] ?string $code = null,
        #[MapQueryParameter] ?string $type = null,
        #[MapQueryParameter] ?string $status = null,
    ): JsonResponse {
        $merchant = $this->getMerchant($user, $merchantRepository);
        if (!$merchant) {
            return $this->json(['error' => 'Merchant not found'], Response::HTTP_FORBIDDEN);
        }

        $result = $warehouseRepository->findByMerchantPaginated(
            $merchant,
            $page,
            $limit,
            $name,
            $code,
            $type,
            $status
        );

        return $this->json([
            'data' => array_map(fn (Warehouse $w) => $this->formatWarehouse($w), $result['data']),
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * 获取仓库详情.
     */
    #[Route('/{id}', methods: ['GET'])]
    public function detail(
        string $id,
        WarehouseRepository $warehouseRepository,
        MerchantRepository $merchantRepository,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $merchant = $this->getMerchant($user, $merchantRepository);
        if (!$merchant) {
            return $this->json(['error' => 'Merchant not found'], Response::HTTP_FORBIDDEN);
        }

        $warehouse = $warehouseRepository->find($id);
        if (!$warehouse || !$this->isOwnedByMerchant($warehouse, $merchant)) {
            return $this->json(['error' => 'Warehouse not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'data' => $this->formatWarehouse($warehouse, true),
        ]);
    }

    /**
     * 创建仓库.
     */
    #[Route('', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateMerchantWarehouseRequest $dto,
        WarehouseRepository $warehouseRepository,
        MerchantRepository $merchantRepository,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $merchant = $this->getMerchant($user, $merchantRepository);
        if (!$merchant) {
            return $this->json(['error' => 'Merchant not found'], Response::HTTP_FORBIDDEN);
        }

        // 检查编码是否已存在
        $existing = $warehouseRepository->findOneBy(['code' => $dto->code]);
        if ($existing) {
            return $this->json(['error' => 'Warehouse code already exists'], Response::HTTP_BAD_REQUEST);
        }

        $warehouse = Warehouse::createMerchantWarehouse(
            $merchant,
            $dto->code,
            $dto->name
        );

        // 设置可选字段
        if ($dto->shortName) {
            $warehouse->setShortName($dto->shortName);
        }
        $warehouse->setType($dto->type);
        $warehouse->setDescription($dto->description);
        $warehouse->setCountryCode($dto->countryCode);
        $warehouse->setProvince($dto->province);
        $warehouse->setCity($dto->city);
        $warehouse->setDistrict($dto->district);
        $warehouse->setAddress($dto->address);
        $warehouse->setPostalCode($dto->postalCode);
        $warehouse->setContactName($dto->contactName ?? '');
        $warehouse->setContactPhone($dto->contactPhone ?? '');
        $warehouse->setContactEmail($dto->contactEmail ?? '');
        $warehouse->setStatus($dto->status);

        $warehouseRepository->save($warehouse, true);

        return $this->json([
            'message' => 'Warehouse created successfully',
            'data' => $this->formatWarehouse($warehouse),
        ], Response::HTTP_CREATED);
    }

    /**
     * 更新仓库.
     */
    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        string $id,
        #[MapRequestPayload] UpdateMerchantWarehouseRequest $dto,
        WarehouseRepository $warehouseRepository,
        EntityManagerInterface $entityManager,
        MerchantRepository $merchantRepository,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $merchant = $this->getMerchant($user, $merchantRepository);
        if (!$merchant) {
            return $this->json(['error' => 'Merchant not found'], Response::HTTP_FORBIDDEN);
        }

        $warehouse = $warehouseRepository->find($id);
        if (!$warehouse || !$this->isOwnedByMerchant($warehouse, $merchant)) {
            return $this->json(['error' => 'Warehouse not found'], Response::HTTP_NOT_FOUND);
        }

        // 更新字段
        if (null !== $dto->name) {
            $warehouse->setName($dto->name);
        }
        if (null !== $dto->shortName) {
            $warehouse->setShortName($dto->shortName);
        }
        if (null !== $dto->type) {
            $warehouse->setType($dto->type);
        }
        if (null !== $dto->description) {
            $warehouse->setDescription($dto->description);
        }
        if (null !== $dto->countryCode) {
            $warehouse->setCountryCode($dto->countryCode);
        }
        if (null !== $dto->province) {
            $warehouse->setProvince($dto->province);
        }
        if (null !== $dto->city) {
            $warehouse->setCity($dto->city);
        }
        if (null !== $dto->district) {
            $warehouse->setDistrict($dto->district);
        }
        if (null !== $dto->address) {
            $warehouse->setAddress($dto->address);
        }
        if (null !== $dto->postalCode) {
            $warehouse->setPostalCode($dto->postalCode);
        }
        if (null !== $dto->contactName) {
            $warehouse->setContactName($dto->contactName);
        }
        if (null !== $dto->contactPhone) {
            $warehouse->setContactPhone($dto->contactPhone);
        }
        if (null !== $dto->contactEmail) {
            $warehouse->setContactEmail($dto->contactEmail);
        }
        if (null !== $dto->status) {
            $warehouse->setStatus($dto->status);
        }

        $entityManager->flush();

        return $this->json([
            'message' => 'Warehouse updated successfully',
            'data' => $this->formatWarehouse($warehouse),
        ]);
    }

    /**
     * 删除仓库.
     */
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(
        string $id,
        WarehouseRepository $warehouseRepository,
        MerchantRepository $merchantRepository,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $merchant = $this->getMerchant($user, $merchantRepository);
        if (!$merchant) {
            return $this->json(['error' => 'Merchant not found'], Response::HTTP_FORBIDDEN);
        }

        $warehouse = $warehouseRepository->find($id);
        if (!$warehouse || !$this->isOwnedByMerchant($warehouse, $merchant)) {
            return $this->json(['error' => 'Warehouse not found'], Response::HTTP_NOT_FOUND);
        }

        $warehouseRepository->remove($warehouse, true);

        return $this->json(['message' => 'Warehouse deleted successfully']);
    }

    /**
     * 获取当前商户.
     */
    private function getMerchant(User $user, MerchantRepository $merchantRepository): ?Merchant
    {
        return $merchantRepository->findByUser($user);
    }

    /**
     * 验证仓库是否属于指定商户.
     */
    private function isOwnedByMerchant(Warehouse $warehouse, Merchant $merchant): bool
    {
        return $warehouse->getMerchant()?->getId() === $merchant->getId()
            && $warehouse->getCategory() === Warehouse::CATEGORY_MERCHANT;
    }

    /**
     * 格式化仓库数据.
     */
    private function formatWarehouse(Warehouse $warehouse, bool $detail = false): array
    {
        $data = [
            'id' => $warehouse->getId(),
            'code' => $warehouse->getCode(),
            'name' => $warehouse->getName(),
            'shortName' => $warehouse->getShortName(),
            'type' => $warehouse->getType(),
            'status' => $warehouse->getStatus(),
            'countryCode' => $warehouse->getCountryCode(),
            'province' => $warehouse->getProvince(),
            'city' => $warehouse->getCity(),
            'contactName' => $warehouse->getContactName(),
            'contactPhone' => $warehouse->getContactPhone(),
            'createdAt' => $warehouse->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $warehouse->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];

        if ($detail) {
            $data['description'] = $warehouse->getDescription();
            $data['district'] = $warehouse->getDistrict();
            $data['address'] = $warehouse->getAddress();
            $data['postalCode'] = $warehouse->getPostalCode();
            $data['contactEmail'] = $warehouse->getContactEmail();
            $data['fullAddress'] = $warehouse->getFullAddress();
        }

        return $data;
    }
}
