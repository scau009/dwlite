<?php

namespace App\Controller\Admin;

use App\Attribute\AdminOnly;
use App\Dto\Admin\CreateWarehouseRequest;
use App\Dto\Admin\Query\WarehouseListQuery;
use App\Dto\Admin\UpdateWarehouseRequest;
use App\Entity\Warehouse;
use App\Repository\MerchantRepository;
use App\Repository\WarehouseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/admin/warehouses')]
#[AdminOnly]
class WarehouseController extends AbstractController
{
    public function __construct(
        private WarehouseRepository $warehouseRepository,
        private MerchantRepository $merchantRepository,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'admin_warehouse_list', methods: ['GET'])]
    public function list(#[MapQueryString] WarehouseListQuery $query = new WarehouseListQuery()): JsonResponse
    {
        $result = $this->warehouseRepository->findPaginated(
            $query->getPage(),
            $query->getLimit(),
            $query->toFilters()
        );

        return $this->json([
            'data' => array_map(fn (Warehouse $w) => $this->serializeWarehouse($w), $result['data']),
            'total' => $result['total'],
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
        ]);
    }

    #[Route('/{id}', name: 'admin_warehouse_detail', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        $warehouse = $this->warehouseRepository->find($id);
        if (!$warehouse) {
            return $this->json(['error' => $this->translator->trans('admin.warehouse.not_found')], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeWarehouse($warehouse, true));
    }

    #[Route('', name: 'admin_warehouse_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateWarehouseRequest $dto): JsonResponse
    {
        // Check if code already exists
        $existing = $this->warehouseRepository->findOneBy(['code' => $dto->code]);
        if ($existing) {
            return $this->json(['error' => $this->translator->trans('admin.warehouse.code_exists')], Response::HTTP_BAD_REQUEST);
        }

        $warehouse = new Warehouse();
        $this->applyDtoToWarehouse($warehouse, $dto);

        // Handle merchant for merchant-category warehouses
        if ($dto->category === Warehouse::CATEGORY_MERCHANT && $dto->merchantId) {
            $merchant = $this->merchantRepository->find($dto->merchantId);
            if ($merchant) {
                $warehouse->setMerchant($merchant);
            }
        }

        $this->warehouseRepository->save($warehouse, true);

        return $this->json([
            'message' => $this->translator->trans('admin.warehouse.created'),
            'data' => $this->serializeWarehouse($warehouse, true),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'admin_warehouse_update', methods: ['PUT'])]
    public function update(string $id, #[MapRequestPayload] UpdateWarehouseRequest $dto): JsonResponse
    {
        $warehouse = $this->warehouseRepository->find($id);
        if (!$warehouse) {
            return $this->json(['error' => $this->translator->trans('admin.warehouse.not_found')], Response::HTTP_NOT_FOUND);
        }

        // Check if code already exists (if being changed)
        if ($dto->code !== null && $dto->code !== $warehouse->getCode()) {
            $existing = $this->warehouseRepository->findOneBy(['code' => $dto->code]);
            if ($existing) {
                return $this->json(['error' => $this->translator->trans('admin.warehouse.code_exists')], Response::HTTP_BAD_REQUEST);
            }
        }

        $this->applyUpdateDtoToWarehouse($warehouse, $dto);

        // Handle merchant for merchant-category warehouses
        if ($dto->category === Warehouse::CATEGORY_MERCHANT && $dto->merchantId !== null) {
            $merchant = $this->merchantRepository->find($dto->merchantId);
            $warehouse->setMerchant($merchant);
        } elseif ($dto->category === Warehouse::CATEGORY_PLATFORM) {
            $warehouse->setMerchant(null);
        }

        $this->warehouseRepository->save($warehouse, true);

        return $this->json([
            'message' => $this->translator->trans('admin.warehouse.updated'),
            'data' => $this->serializeWarehouse($warehouse, true),
        ]);
    }

    #[Route('/{id}', name: 'admin_warehouse_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $warehouse = $this->warehouseRepository->find($id);
        if (!$warehouse) {
            return $this->json(['error' => $this->translator->trans('admin.warehouse.not_found')], Response::HTTP_NOT_FOUND);
        }

        $this->warehouseRepository->remove($warehouse, true);

        return $this->json([
            'message' => $this->translator->trans('admin.warehouse.deleted'),
        ]);
    }

    private function applyDtoToWarehouse(Warehouse $warehouse, CreateWarehouseRequest $dto): void
    {
        $warehouse->setCode($dto->code);
        $warehouse->setName($dto->name);
        $warehouse->setShortName($dto->shortName);
        $warehouse->setType($dto->type);
        $warehouse->setCategory($dto->category);
        $warehouse->setDescription($dto->description);
        $warehouse->setCountryCode($dto->countryCode);
        $warehouse->setTimezone($dto->timezone);
        $warehouse->setProvince($dto->province);
        $warehouse->setCity($dto->city);
        $warehouse->setDistrict($dto->district);
        $warehouse->setAddress($dto->address);
        $warehouse->setPostalCode($dto->postalCode);
        $warehouse->setLongitude($dto->longitude);
        $warehouse->setLatitude($dto->latitude);
        $warehouse->setContactName($dto->contactName ?? '');
        $warehouse->setContactPhone($dto->contactPhone ?? '');
        $warehouse->setContactEmail($dto->contactEmail);
        $warehouse->setInternalNotes($dto->internalNotes);
        $warehouse->setStatus($dto->status);
        $warehouse->setSortOrder($dto->sortOrder);
    }

    private function applyUpdateDtoToWarehouse(Warehouse $warehouse, UpdateWarehouseRequest $dto): void
    {
        if ($dto->code !== null) {
            $warehouse->setCode($dto->code);
        }
        if ($dto->name !== null) {
            $warehouse->setName($dto->name);
        }
        if ($dto->shortName !== null) {
            $warehouse->setShortName($dto->shortName);
        }
        if ($dto->type !== null) {
            $warehouse->setType($dto->type);
        }
        if ($dto->category !== null) {
            $warehouse->setCategory($dto->category);
        }
        if ($dto->description !== null) {
            $warehouse->setDescription($dto->description);
        }
        if ($dto->countryCode !== null) {
            $warehouse->setCountryCode($dto->countryCode);
        }
        if ($dto->timezone !== null) {
            $warehouse->setTimezone($dto->timezone);
        }
        if ($dto->province !== null) {
            $warehouse->setProvince($dto->province);
        }
        if ($dto->city !== null) {
            $warehouse->setCity($dto->city);
        }
        if ($dto->district !== null) {
            $warehouse->setDistrict($dto->district);
        }
        if ($dto->address !== null) {
            $warehouse->setAddress($dto->address);
        }
        if ($dto->postalCode !== null) {
            $warehouse->setPostalCode($dto->postalCode);
        }
        if ($dto->longitude !== null) {
            $warehouse->setLongitude($dto->longitude);
        }
        if ($dto->latitude !== null) {
            $warehouse->setLatitude($dto->latitude);
        }
        if ($dto->contactName !== null) {
            $warehouse->setContactName($dto->contactName);
        }
        if ($dto->contactPhone !== null) {
            $warehouse->setContactPhone($dto->contactPhone);
        }
        if ($dto->contactEmail !== null) {
            $warehouse->setContactEmail($dto->contactEmail);
        }
        if ($dto->internalNotes !== null) {
            $warehouse->setInternalNotes($dto->internalNotes);
        }
        if ($dto->status !== null) {
            $warehouse->setStatus($dto->status);
        }
        if ($dto->sortOrder !== null) {
            $warehouse->setSortOrder($dto->sortOrder);
        }
    }

    private function serializeWarehouse(Warehouse $warehouse, bool $detail = false): array
    {
        $data = [
            'id' => $warehouse->getId(),
            'code' => $warehouse->getCode(),
            'name' => $warehouse->getName(),
            'shortName' => $warehouse->getShortName(),
            'type' => $warehouse->getType(),
            'category' => $warehouse->getCategory(),
            'countryCode' => $warehouse->getCountryCode(),
            'status' => $warehouse->getStatus(),
            'sortOrder' => $warehouse->getSortOrder(),
            'createdAt' => $warehouse->getCreatedAt()->format('c'),
            'updatedAt' => $warehouse->getUpdatedAt()->format('c'),
        ];

        // Merchant info
        if ($warehouse->getMerchant()) {
            $data['merchant'] = [
                'id' => $warehouse->getMerchant()->getId(),
                'name' => $warehouse->getMerchant()->getName(),
            ];
        }

        // Location summary
        $data['fullAddress'] = $warehouse->getFullAddress();
        $data['city'] = $warehouse->getCity();
        $data['province'] = $warehouse->getProvince();

        // Contact summary
        $data['contactName'] = $warehouse->getContactName();
        $data['contactPhone'] = $warehouse->getContactPhone();

        if ($detail) {
            $data['description'] = $warehouse->getDescription();
            $data['timezone'] = $warehouse->getTimezone();
            $data['district'] = $warehouse->getDistrict();
            $data['address'] = $warehouse->getAddress();
            $data['postalCode'] = $warehouse->getPostalCode();
            $data['longitude'] = $warehouse->getLongitude();
            $data['latitude'] = $warehouse->getLatitude();
            $data['contactEmail'] = $warehouse->getContactEmail();
            $data['internalNotes'] = $warehouse->getInternalNotes();
        }

        return $data;
    }
}
