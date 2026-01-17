<?php

namespace App\Controller\Admin;

use App\Attribute\AdminOnly;
use App\Dto\Admin\CreateApiKeyRequest;
use App\Dto\Admin\Query\ApiKeyListQuery;
use App\Dto\Admin\UpdateApiKeyIpWhitelistRequest;
use App\Dto\Admin\UpdateApiKeyPermissionsRequest;
use App\Dto\Admin\UpdateApiKeyStatusRequest;
use App\Entity\ApiKey;
use App\Entity\User;
use App\Repository\ApiKeyRepository;
use App\Repository\MerchantRepository;
use App\Repository\WarehouseRepository;
use App\Service\OpenApi\ApiKeyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/admin/api-keys')]
#[AdminOnly]
class ApiKeyController extends AbstractController
{
    public function __construct(
        private ApiKeyRepository $apiKeyRepository,
        private ApiKeyService $apiKeyService,
        private MerchantRepository $merchantRepository,
        private WarehouseRepository $warehouseRepository,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'admin_api_key_list', methods: ['GET'])]
    public function list(#[MapQueryString] ApiKeyListQuery $query = new ApiKeyListQuery()): JsonResponse
    {
        $result = $this->apiKeyRepository->findPaginated(
            $query->getPage(),
            $query->getLimit(),
            $query->toFilters()
        );

        return $this->json([
            'data' => array_map(fn (ApiKey $k) => $this->serializeApiKey($k), $result['data']),
            'total' => $result['total'],
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
        ]);
    }

    #[Route('/{id}', name: 'admin_api_key_detail', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        $apiKey = $this->apiKeyRepository->find($id);
        if (!$apiKey) {
            return $this->json(['error' => $this->translator->trans('api_key.not_found')], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeApiKey($apiKey, true));
    }

    #[Route('', name: 'admin_api_key_create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateApiKeyRequest $dto,
        #[CurrentUser] User $user
    ): JsonResponse {
        // Validate type-specific requirements
        if ($dto->type === ApiKey::TYPE_WAREHOUSE) {
            if (empty($dto->warehouseId)) {
                return $this->json(['error' => $this->translator->trans('api_key.warehouse_id_required')], Response::HTTP_BAD_REQUEST);
            }

            $warehouse = $this->warehouseRepository->find($dto->warehouseId);
            if (!$warehouse) {
                return $this->json(['error' => $this->translator->trans('warehouse.not_found')], Response::HTTP_NOT_FOUND);
            }

            $result = $this->apiKeyService->createWarehouseApiKey(
                $warehouse,
                $dto->name,
                $dto->permissions,
                $user,
                $dto->expiresAt,
                $dto->ipWhitelist
            );
        } elseif ($dto->type === ApiKey::TYPE_MERCHANT) {
            if (empty($dto->merchantId)) {
                return $this->json(['error' => $this->translator->trans('api_key.merchant_id_required')], Response::HTTP_BAD_REQUEST);
            }

            $merchant = $this->merchantRepository->find($dto->merchantId);
            if (!$merchant) {
                return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
            }

            $result = $this->apiKeyService->createMerchantApiKey(
                $merchant,
                $dto->name,
                $dto->permissions,
                $user,
                $dto->expiresAt,
                $dto->ipWhitelist
            );
        } else {
            return $this->json(['error' => $this->translator->trans('api_key.invalid_type')], Response::HTTP_BAD_REQUEST);
        }

        $apiKey = $result['apiKey'];
        $plainSecret = $result['plainSecret'];

        return $this->json([
            'message' => $this->translator->trans('api_key.created'),
            'apiKey' => $this->serializeApiKey($apiKey, true),
            'secret' => $plainSecret, // Only shown once
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/status', name: 'admin_api_key_status', methods: ['PUT'])]
    public function updateStatus(string $id, #[MapRequestPayload] UpdateApiKeyStatusRequest $dto): JsonResponse
    {
        $apiKey = $this->apiKeyRepository->find($id);
        if (!$apiKey) {
            return $this->json(['error' => $this->translator->trans('api_key.not_found')], Response::HTTP_NOT_FOUND);
        }

        if ($dto->status === 'active') {
            $this->apiKeyService->activateApiKey($apiKey);
            $message = $this->translator->trans('api_key.activated');
        } else {
            $this->apiKeyService->suspendApiKey($apiKey);
            $message = $this->translator->trans('api_key.suspended');
        }

        return $this->json([
            'message' => $message,
            'apiKey' => $this->serializeApiKey($apiKey),
        ]);
    }

    #[Route('/{id}/permissions', name: 'admin_api_key_permissions', methods: ['PUT'])]
    public function updatePermissions(string $id, #[MapRequestPayload] UpdateApiKeyPermissionsRequest $dto): JsonResponse
    {
        $apiKey = $this->apiKeyRepository->find($id);
        if (!$apiKey) {
            return $this->json(['error' => $this->translator->trans('api_key.not_found')], Response::HTTP_NOT_FOUND);
        }

        $this->apiKeyService->updatePermissions($apiKey, $dto->permissions);

        return $this->json([
            'message' => $this->translator->trans('api_key.permissions_updated'),
            'apiKey' => $this->serializeApiKey($apiKey),
        ]);
    }

    #[Route('/{id}/ip-whitelist', name: 'admin_api_key_ip_whitelist', methods: ['PUT'])]
    public function updateIpWhitelist(string $id, #[MapRequestPayload] UpdateApiKeyIpWhitelistRequest $dto): JsonResponse
    {
        $apiKey = $this->apiKeyRepository->find($id);
        if (!$apiKey) {
            return $this->json(['error' => $this->translator->trans('api_key.not_found')], Response::HTTP_NOT_FOUND);
        }

        $this->apiKeyService->updateIpWhitelist($apiKey, $dto->ipWhitelist);

        return $this->json([
            'message' => $this->translator->trans('api_key.ip_whitelist_updated'),
            'apiKey' => $this->serializeApiKey($apiKey, true),
        ]);
    }

    #[Route('/{id}', name: 'admin_api_key_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $apiKey = $this->apiKeyRepository->find($id);
        if (!$apiKey) {
            return $this->json(['error' => $this->translator->trans('api_key.not_found')], Response::HTTP_NOT_FOUND);
        }

        $this->apiKeyService->deleteApiKey($apiKey);

        return $this->json([
            'message' => $this->translator->trans('api_key.deleted'),
        ]);
    }

    #[Route('/permissions/warehouse', name: 'admin_api_key_warehouse_permissions', methods: ['GET'])]
    public function getWarehousePermissions(): JsonResponse
    {
        return $this->json([
            'permissions' => ApiKey::getDefaultWarehousePermissions(),
        ]);
    }

    #[Route('/permissions/merchant', name: 'admin_api_key_merchant_permissions', methods: ['GET'])]
    public function getMerchantPermissions(): JsonResponse
    {
        return $this->json([
            'permissions' => ApiKey::getDefaultMerchantPermissions(),
        ]);
    }

    private function serializeApiKey(ApiKey $apiKey, bool $detail = false): array
    {
        $data = [
            'id' => $apiKey->getId(),
            'keyId' => $apiKey->getKeyId(),
            'name' => $apiKey->getName(),
            'type' => $apiKey->getType(),
            'status' => $apiKey->getStatus(),
            'permissions' => $apiKey->getPermissions(),
            'lastUsedAt' => $apiKey->getLastUsedAt()?->format('c'),
            'expiresAt' => $apiKey->getExpiresAt()?->format('c'),
            'createdAt' => $apiKey->getCreatedAt()->format('c'),
            'updatedAt' => $apiKey->getUpdatedAt()->format('c'),
        ];

        if ($apiKey->isWarehouseType()) {
            $warehouse = $apiKey->getWarehouse();
            $data['warehouse'] = $warehouse ? [
                'id' => $warehouse->getId(),
                'code' => $warehouse->getCode(),
                'name' => $warehouse->getName(),
            ] : null;
        } elseif ($apiKey->isMerchantType()) {
            $merchant = $apiKey->getMerchant();
            $data['merchant'] = $merchant ? [
                'id' => $merchant->getId(),
                'name' => $merchant->getName(),
            ] : null;
        }

        if ($detail) {
            $data['ipWhitelist'] = $apiKey->getIpWhitelist();
            $createdBy = $apiKey->getCreatedBy();
            $data['createdBy'] = $createdBy ? [
                'id' => $createdBy->getId(),
                'email' => $createdBy->getEmail(),
            ] : null;
        }

        return $data;
    }
}
