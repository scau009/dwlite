<?php

namespace App\Controller;

use App\Dto\Admin\Query\PaginationQuery;
use App\Dto\Merchant\BatchCreateListingRequest;
use App\Dto\Merchant\CalculateListingDefaultsRequest;
use App\Dto\Merchant\CreateListingRequest;
use App\Dto\Merchant\UpdateListingRequest;
use App\Entity\InventoryListing;
use App\Entity\MerchantInventory;
use App\Entity\MerchantSalesChannel;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\InventoryListingRepository;
use App\Repository\MerchantInventoryRepository;
use App\Repository\MerchantRepository;
use App\Repository\MerchantSalesChannelRepository;
use App\Service\CosService;
use App\Service\InventoryListingService;
use App\Service\RuleEngine\MerchantRuleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 商户自助服务 - 库存上架管理.
 */
#[Route('/api/merchant/listings')]
class MerchantListingController extends AbstractController
{
    public function __construct(
        private MerchantRepository $merchantRepository,
        private InventoryListingRepository $listingRepository,
        private MerchantInventoryRepository $inventoryRepository,
        private MerchantSalesChannelRepository $channelRepository,
        private InventoryListingService $listingService,
        private MerchantRuleService $merchantRuleService,
        private TranslatorInterface $translator,
        private CosService $cosService,
    ) {
    }

    /**
     * 获取上架列表.
     */
    #[Route('', name: 'merchant_listings_list', methods: ['GET'])]
    public function list(
        #[CurrentUser] User $user,
        #[MapQueryString] PaginationQuery $query = new PaginationQuery(),
        ?Request $request = null
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $filters = [];
        if ($request) {
            if ($channelId = $request->query->get('channelId')) {
                $filters['channelId'] = $channelId;
            }
            if ($status = $request->query->get('status')) {
                $filters['status'] = $status;
            }
            if ($fulfillmentType = $request->query->get('fulfillmentType')) {
                $filters['fulfillmentType'] = $fulfillmentType;
            }
            if ($pricingModel = $request->query->get('pricingModel')) {
                $filters['pricingModel'] = $pricingModel;
            }
            if ($search = $request->query->get('search')) {
                $filters['search'] = $search;
            }
        }

        $result = $this->listingRepository->findByMerchantPaginated(
            $merchant,
            $query->getPage(),
            $query->getLimit(),
            $filters
        );

        return $this->json([
            'data' => array_map(fn (InventoryListing $l) => $this->serializeListing($l), $result['data']),
            'total' => $result['total'],
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
        ]);
    }

    /**
     * 获取单个上架详情.
     */
    #[Route('/{id}', name: 'merchant_listings_show', methods: ['GET'])]
    public function show(
        #[CurrentUser] User $user,
        string $id
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $listing = $this->listingRepository->find($id);
        if (!$listing || $listing->getMerchantInventory()->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => $this->translator->trans('listing.notFound')], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['data' => $this->serializeListing($listing)]);
    }

    /**
     * 创建上架.
     */
    #[Route('', name: 'merchant_listings_create', methods: ['POST'])]
    public function create(
        #[CurrentUser] User $user,
        #[MapRequestPayload] CreateListingRequest $dto
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        try {
            $listing = $this->listingService->createListing($merchant, $dto);

            return $this->json(['data' => $this->serializeListing($listing)], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 批量创建上架.
     */
    #[Route('/batch', name: 'merchant_listings_batch_create', methods: ['POST'], priority: 10)]
    public function batchCreate(
        #[CurrentUser] User $user,
        #[MapRequestPayload] BatchCreateListingRequest $dto
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $results = [
            'success' => [],
            'failed' => [],
        ];

        foreach ($dto->listings as $index => $item) {
            try {
                $createDto = new CreateListingRequest();
                $createDto->merchantInventoryId = $item->merchantInventoryId;
                $createDto->merchantSalesChannelId = $dto->merchantSalesChannelId;
                $createDto->fulfillmentType = $item->fulfillmentType;
                $createDto->pricingModel = $item->pricingModel;
                $createDto->allocationMode = $item->allocationMode;
                $createDto->allocatedQuantity = $item->allocatedQuantity;
                $createDto->price = $item->price;
                $createDto->compareAtPrice = $item->compareAtPrice;
                $createDto->remark = $item->remark;

                $listing = $this->listingService->createListing($merchant, $createDto);
                $results['success'][] = [
                    'index' => $index,
                    'inventoryId' => $item->merchantInventoryId,
                    'listing' => $this->serializeListing($listing),
                ];
            } catch (\InvalidArgumentException $e) {
                $results['failed'][] = [
                    'index' => $index,
                    'inventoryId' => $item->merchantInventoryId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $statusCode = empty($results['failed']) ? Response::HTTP_CREATED : Response::HTTP_MULTI_STATUS;

        return $this->json([
            'message' => $this->translator->trans('listing.batchCreated', [
                '%success%' => count($results['success']),
                '%failed%' => count($results['failed']),
            ]),
            'successCount' => count($results['success']),
            'failedCount' => count($results['failed']),
            'results' => $results,
        ], $statusCode);
    }

    /**
     * 根据渠道规则计算上架默认值.
     */
    #[Route('/calculate-defaults', name: 'merchant_listings_calculate_defaults', methods: ['POST'], priority: 10)]
    public function calculateDefaults(
        #[CurrentUser] User $user,
        #[MapRequestPayload] CalculateListingDefaultsRequest $dto
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $channel = $this->channelRepository->find($dto->merchantSalesChannelId);
        if (!$channel || $channel->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => $this->translator->trans('listing.channelNotFound')], Response::HTTP_NOT_FOUND);
        }

        $results = [];

        foreach ($dto->inventoryIds as $inventoryId) {
            $inventory = $this->inventoryRepository->find($inventoryId);
            if (!$inventory || $inventory->getMerchant()->getId() !== $merchant->getId()) {
                continue;
            }

            // 获取库存成本价
            $baseCost = (float) ($inventory->getAverageCost() ?? 0);

            // 计算定价默认值
            $pricingResult = $this->merchantRuleService->calculatePriceWithDetails(
                $channel,
                $inventory,
                $baseCost
            );

            // 计算库存分配默认值
            $allocationResult = $this->merchantRuleService->calculateStockAllocationWithDetails(
                $channel,
                $inventory
            );

            $results[] = [
                'inventoryId' => $inventoryId,
                'pricing' => [
                    'calculatedPrice' => $pricingResult['price'],
                    'baseCost' => $pricingResult['baseCost'],
                    'hasRules' => $pricingResult['hasRules'],
                    'rules' => $pricingResult['rules'],
                ],
                'allocation' => [
                    'calculatedQuantity' => $allocationResult['allocation'],
                    'availableQuantity' => $allocationResult['availableQuantity'],
                    'hasRules' => $allocationResult['hasRules'],
                    'rules' => $allocationResult['rules'],
                ],
            ];
        }

        return $this->json(['data' => $results]);
    }

    /**
     * 更新上架（只能修改价格、备注、分配数量）.
     */
    #[Route('/{id}', name: 'merchant_listings_update', methods: ['PUT'])]
    public function update(
        #[CurrentUser] User $user,
        string $id,
        #[MapRequestPayload] UpdateListingRequest $dto
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $listing = $this->listingRepository->find($id);
        if (!$listing || $listing->getMerchantInventory()->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => $this->translator->trans('listing.notFound')], Response::HTTP_NOT_FOUND);
        }

        try {
            $listing = $this->listingService->updateListing($listing, $dto);

            return $this->json(['data' => $this->serializeListing($listing)]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 激活上架.
     */
    #[Route('/{id}/activate', name: 'merchant_listings_activate', methods: ['POST'])]
    public function activate(
        #[CurrentUser] User $user,
        string $id
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $listing = $this->listingRepository->find($id);
        if (!$listing || $listing->getMerchantInventory()->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => $this->translator->trans('listing.notFound')], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->listingService->activateListing($listing);

            return $this->json(['data' => $this->serializeListing($listing)]);
        } catch (\InvalidArgumentException|\LogicException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 暂停上架.
     */
    #[Route('/{id}/pause', name: 'merchant_listings_pause', methods: ['POST'])]
    public function pause(
        #[CurrentUser] User $user,
        string $id
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $listing = $this->listingRepository->find($id);
        if (!$listing || $listing->getMerchantInventory()->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => $this->translator->trans('listing.notFound')], Response::HTTP_NOT_FOUND);
        }

        $this->listingService->pauseListing($listing);

        return $this->json(['data' => $this->serializeListing($listing)]);
    }

    /**
     * 删除上架（仅草稿状态）.
     */
    #[Route('/{id}', name: 'merchant_listings_delete', methods: ['DELETE'])]
    public function delete(
        #[CurrentUser] User $user,
        string $id
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $listing = $this->listingRepository->find($id);
        if (!$listing || $listing->getMerchantInventory()->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => $this->translator->trans('listing.notFound')], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->listingService->deleteListing($listing);

            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 获取可上架的库存列表（排除已在该渠道上架的）.
     */
    #[Route('/available-inventory', name: 'merchant_listings_available_inventory', methods: ['GET'], priority: 10)]
    public function availableInventory(
        #[CurrentUser] User $user,
        #[MapQueryString] PaginationQuery $query = new PaginationQuery(),
        ?Request $request = null
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $channelId = $request?->query->get('channelId');
        if (!$channelId) {
            return $this->json(['error' => $this->translator->trans('listing.channelIdRequired')], Response::HTTP_BAD_REQUEST);
        }

        $channel = $this->channelRepository->find($channelId);
        if (!$channel || $channel->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => $this->translator->trans('listing.channelNotFound')], Response::HTTP_NOT_FOUND);
        }

        $search = $request?->query->get('search');

        $data = $this->listingService->getAvailableInventoryForChannel(
            $merchant,
            $channel,
            $query->getPage(),
            $query->getLimit(),
            $search
        );

        $total = $this->listingService->countAvailableInventoryForChannel(
            $merchant,
            $channel,
            $search
        );

        return $this->json([
            'data' => array_map(fn (MerchantInventory $i) => $this->serializeInventory($i), $data),
            'total' => $total,
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
        ]);
    }

    /**
     * 获取商户的活跃渠道列表（用于创建上架时选择）.
     */
    #[Route('/available-channels', name: 'merchant_listings_available_channels', methods: ['GET'], priority: 10)]
    public function availableChannels(
        #[CurrentUser] User $user
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $channels = $this->channelRepository->findByMerchant($merchant);
        $activeChannels = array_filter($channels, fn (MerchantSalesChannel $c) => $c->isActive());

        return $this->json([
            'data' => array_map(fn (MerchantSalesChannel $c) => $this->serializeChannelForListing($c), array_values($activeChannels)),
        ]);
    }

    private function serializeListing(InventoryListing $listing): array
    {
        $inventory = $listing->getMerchantInventory();
        $sku = $inventory->getProductSku();
        $product = $sku->getProduct();
        $channel = $listing->getMerchantSalesChannel();
        $salesChannel = $channel->getSalesChannel();

        return [
            'id' => $listing->getId(),
            'merchantInventory' => [
                'id' => $inventory->getId(),
                'warehouse' => [
                    'id' => $inventory->getWarehouse()->getId(),
                    'name' => $inventory->getWarehouse()->getName(),
                    'code' => $inventory->getWarehouse()->getCode(),
                    'category' => $inventory->getWarehouse()->getCategory(),
                ],
                'quantityAvailable' => $inventory->getQuantityAvailable(),
                'quantityAllocated' => $inventory->getQuantityAllocated(),
            ],
            'productSku' => [
                'id' => $sku->getId(),
                'sizeValue' => $sku->getSizeValue(),
                'sizeUnit' => $sku->getSizeUnit(),
                'barcode' => $sku->getBarcode(),
            ],
            'product' => [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'styleNumber' => $product->getStyleNumber(),
                'color' => $product->getColor(),
                'imageUrl' => $this->getProductImageUrl($product),
            ],
            'merchantSalesChannel' => [
                'id' => $channel->getId(),
                'approvedFulfillmentTypes' => $channel->getApprovedFulfillmentTypes(),
            ],
            'salesChannel' => [
                'id' => $salesChannel->getId(),
                'name' => $salesChannel->getName(),
                'code' => $salesChannel->getCode(),
                'logoUrl' => $salesChannel->getLogoUrl(),
                'currency' => $salesChannel->getCurrency(),
            ],
            'fulfillmentType' => $listing->getFulfillmentType(),
            'pricingModel' => $listing->getPricingModel(),
            'allocationMode' => $listing->getAllocationMode(),
            'allocatedQuantity' => $listing->getAllocatedQuantity(),
            'soldQuantity' => $listing->getSoldQuantity(),
            'availableQuantity' => $listing->getAvailableQuantity(),
            'price' => $listing->getPrice(),
            'compareAtPrice' => $listing->getCompareAtPrice(),
            'status' => $listing->getStatus(),
            'remark' => $listing->getRemark(),
            'createdAt' => $listing->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $listing->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function serializeInventory(MerchantInventory $inventory): array
    {
        $sku = $inventory->getProductSku();
        $product = $sku->getProduct();
        $warehouse = $inventory->getWarehouse();

        return [
            'id' => $inventory->getId(),
            'warehouse' => [
                'id' => $warehouse->getId(),
                'name' => $warehouse->getName(),
                'code' => $warehouse->getCode(),
                'category' => $warehouse->getCategory(),
            ],
            'productSku' => [
                'id' => $sku->getId(),
                'sizeValue' => $sku->getSizeValue(),
                'sizeUnit' => $sku->getSizeUnit(),
            ],
            'product' => [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'styleNumber' => $product->getStyleNumber(),
                'color' => $product->getColor(),
                'imageUrl' => $this->getProductImageUrl($product),
            ],
            'quantityAvailable' => $inventory->getQuantityAvailable(),
            'quantityAllocated' => $inventory->getQuantityAllocated(),
            'shareableQuantity' => $inventory->getShareableQuantity(),
        ];
    }

    private function serializeChannelForListing(MerchantSalesChannel $channel): array
    {
        $salesChannel = $channel->getSalesChannel();

        return [
            'id' => $channel->getId(),
            'salesChannel' => [
                'id' => $salesChannel->getId(),
                'name' => $salesChannel->getName(),
                'code' => $salesChannel->getCode(),
                'logoUrl' => $salesChannel->getLogoUrl(),
                'currency' => $salesChannel->getCurrency(),
            ],
            'approvedFulfillmentTypes' => $channel->getApprovedFulfillmentTypes(),
            'status' => $channel->getStatus(),
        ];
    }

    private function getProductImageUrl(Product $product): ?string
    {
        $primaryImage = $product->getPrimaryImage();
        if (!$primaryImage) {
            return null;
        }

        return $this->cosService->getSignedUrl(
            $primaryImage->getCosKey(),
            3600,
            'imageMogr2/thumbnail/300x300>'
        );
    }
}
