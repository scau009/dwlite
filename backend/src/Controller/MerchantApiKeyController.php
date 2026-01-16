<?php

namespace App\Controller;

use App\Dto\Admin\UpdateApiKeyIpWhitelistRequest;
use App\Dto\Admin\UpdateApiKeyPermissionsRequest;
use App\Dto\Merchant\CreateMerchantApiKeyRequest;
use App\Entity\ApiKey;
use App\Entity\User;
use App\Repository\ApiKeyRepository;
use App\Repository\MerchantRepository;
use App\Service\OpenApi\ApiKeyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 商户自助服务 - API Key 管理.
 */
#[Route('/api/merchant/api-keys')]
class MerchantApiKeyController extends AbstractController
{
    private const MAX_API_KEYS_PER_MERCHANT = 1;

    public function __construct(
        private MerchantRepository $merchantRepository,
        private ApiKeyRepository $apiKeyRepository,
        private ApiKeyService $apiKeyService,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * 获取当前商户的所有 API Keys.
     */
    #[Route('', name: 'merchant_api_key_list', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $apiKeys = $this->apiKeyRepository->findByMerchant($merchant);

        return $this->json([
            'data' => array_map(fn (ApiKey $k) => $this->serializeApiKey($k), $apiKeys),
        ]);
    }

    /**
     * 获取 API Key 详情.
     */
    #[Route('/{id}', name: 'merchant_api_key_detail', methods: ['GET'])]
    public function detail(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $apiKey = $this->apiKeyRepository->find($id);
        if (!$apiKey || $apiKey->getMerchant()?->getId() !== $merchant->getId()) {
            return $this->json(['error' => $this->translator->trans('api_key.not_found')], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeApiKey($apiKey, true));
    }

    /**
     * 创建新的 API Key.
     */
    #[Route('', name: 'merchant_api_key_create', methods: ['POST'])]
    public function create(
        #[CurrentUser] User $user,
        #[MapRequestPayload] CreateMerchantApiKeyRequest $dto
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        // 检查商户状态
        if (!$merchant->isApproved()) {
            return $this->json(['error' => $this->translator->trans('merchant.not_approved')], Response::HTTP_FORBIDDEN);
        }

        // 检查 API Key 数量限制
        $existingCount = $this->apiKeyService->countByMerchant($merchant);
        if ($existingCount >= self::MAX_API_KEYS_PER_MERCHANT) {
            return $this->json([
                'error' => $this->translator->trans('apiKeys.maxLimitReached'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->apiKeyService->createMerchantApiKey(
            $merchant,
            $dto->name,
            $dto->permissions,
            $user,
            $dto->expiresAt,
            $dto->ipWhitelist
        );

        $apiKey = $result['apiKey'];
        $plainSecret = $result['plainSecret'];

        return $this->json([
            'message' => $this->translator->trans('api_key.created'),
            'apiKey' => $this->serializeApiKey($apiKey, true),
            'secret' => $plainSecret, // Only shown once
        ], Response::HTTP_CREATED);
    }

    /**
     * 更新 API Key 权限.
     */
    #[Route('/{id}/permissions', name: 'merchant_api_key_permissions', methods: ['PUT'])]
    public function updatePermissions(
        string $id,
        #[CurrentUser] User $user,
        #[MapRequestPayload] UpdateApiKeyPermissionsRequest $dto
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $apiKey = $this->apiKeyRepository->find($id);
        if (!$apiKey || $apiKey->getMerchant()?->getId() !== $merchant->getId()) {
            return $this->json(['error' => $this->translator->trans('api_key.not_found')], Response::HTTP_NOT_FOUND);
        }

        $this->apiKeyService->updatePermissions($apiKey, $dto->permissions);

        return $this->json([
            'message' => $this->translator->trans('api_key.permissions_updated'),
            'apiKey' => $this->serializeApiKey($apiKey),
        ]);
    }

    /**
     * 更新 API Key IP 白名单.
     */
    #[Route('/{id}/ip-whitelist', name: 'merchant_api_key_ip_whitelist', methods: ['PUT'])]
    public function updateIpWhitelist(
        string $id,
        #[CurrentUser] User $user,
        #[MapRequestPayload] UpdateApiKeyIpWhitelistRequest $dto
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $apiKey = $this->apiKeyRepository->find($id);
        if (!$apiKey || $apiKey->getMerchant()?->getId() !== $merchant->getId()) {
            return $this->json(['error' => $this->translator->trans('api_key.not_found')], Response::HTTP_NOT_FOUND);
        }

        $this->apiKeyService->updateIpWhitelist($apiKey, $dto->ipWhitelist);

        return $this->json([
            'message' => $this->translator->trans('api_key.ip_whitelist_updated'),
            'apiKey' => $this->serializeApiKey($apiKey),
        ]);
    }

    /**
     * 停用 API Key.
     */
    #[Route('/{id}/suspend', name: 'merchant_api_key_suspend', methods: ['POST'])]
    public function suspend(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $apiKey = $this->apiKeyRepository->find($id);
        if (!$apiKey || $apiKey->getMerchant()?->getId() !== $merchant->getId()) {
            return $this->json(['error' => $this->translator->trans('api_key.not_found')], Response::HTTP_NOT_FOUND);
        }

        $this->apiKeyService->suspendApiKey($apiKey);

        return $this->json([
            'message' => $this->translator->trans('api_key.suspended'),
            'apiKey' => $this->serializeApiKey($apiKey),
        ]);
    }

    /**
     * 激活 API Key.
     */
    #[Route('/{id}/activate', name: 'merchant_api_key_activate', methods: ['POST'])]
    public function activate(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $apiKey = $this->apiKeyRepository->find($id);
        if (!$apiKey || $apiKey->getMerchant()?->getId() !== $merchant->getId()) {
            return $this->json(['error' => $this->translator->trans('api_key.not_found')], Response::HTTP_NOT_FOUND);
        }

        $this->apiKeyService->activateApiKey($apiKey);

        return $this->json([
            'message' => $this->translator->trans('api_key.activated'),
            'apiKey' => $this->serializeApiKey($apiKey),
        ]);
    }

    /**
     * 重新生成 API Key Secret.
     */
    #[Route('/{id}/regenerate-secret', name: 'merchant_api_key_regenerate_secret', methods: ['POST'])]
    public function regenerateSecret(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $apiKey = $this->apiKeyRepository->find($id);
        if (!$apiKey || $apiKey->getMerchant()?->getId() !== $merchant->getId()) {
            return $this->json(['error' => $this->translator->trans('api_key.not_found')], Response::HTTP_NOT_FOUND);
        }

        $newSecret = $this->apiKeyService->regenerateSecret($apiKey);

        return $this->json([
            'message' => $this->translator->trans('apiKeys.secretRegenerated'),
            'secret' => $newSecret,
        ]);
    }

    /**
     * 删除 API Key.
     */
    #[Route('/{id}', name: 'merchant_api_key_delete', methods: ['DELETE'])]
    public function delete(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $apiKey = $this->apiKeyRepository->find($id);
        if (!$apiKey || $apiKey->getMerchant()?->getId() !== $merchant->getId()) {
            return $this->json(['error' => $this->translator->trans('api_key.not_found')], Response::HTTP_NOT_FOUND);
        }

        $this->apiKeyService->deleteApiKey($apiKey);

        return $this->json([
            'message' => $this->translator->trans('api_key.deleted'),
        ]);
    }

    /**
     * 获取可用的权限列表.
     */
    #[Route('/permissions', name: 'merchant_api_key_permissions_list', methods: ['GET'])]
    public function getAvailablePermissions(): JsonResponse
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

        if ($detail) {
            $data['ipWhitelist'] = $apiKey->getIpWhitelist();
        }

        return $data;
    }
}
