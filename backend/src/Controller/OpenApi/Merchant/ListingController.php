<?php

namespace App\Controller\OpenApi\Merchant;

use App\Attribute\OpenApiOnly;
use App\Dto\Merchant\CreateListingRequest as InternalCreateRequest;
use App\Dto\Merchant\UpdateListingRequest as InternalUpdateRequest;
use App\Dto\OpenApi\Merchant\CreateListingRequest;
use App\Dto\OpenApi\Merchant\ListingQuery;
use App\Dto\OpenApi\Merchant\UpdateListingRequest;
use App\Entity\InventoryListing;
use App\Entity\Merchant;
use App\Repository\InventoryListingRepository;
use App\Repository\MerchantSalesChannelRepository;
use App\Repository\SalesChannelRepository;
use App\Service\InventoryListingService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Merchant Listing API Controller.
 */
#[Route('/api/v1/open/merchant/listings', name: 'open_api_merchant_listing_')]
#[OpenApiOnly(permission: 'listing:read')]
#[OA\Tag(name: 'Merchant - Listing', description: '商户上架管理')]
class ListingController extends AbstractController
{
    public function __construct(
        private readonly InventoryListingRepository $listingRepository,
        private readonly SalesChannelRepository $salesChannelRepository,
        private readonly MerchantSalesChannelRepository $merchantChannelRepository,
        private readonly InventoryListingService $listingService
    ) {
    }

    /**
     * List listings.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        #[MapQueryString] ListingQuery $query,
        Request $request
    ): JsonResponse {
        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('merchant');

        $filters = [];
        if ($query->status !== null) {
            $filters['status'] = $query->status;
        }
        if ($query->channelCode !== null) {
            $salesChannel = $this->salesChannelRepository->findByCode($query->channelCode);
            if ($salesChannel !== null) {
                $merchantChannel = $this->merchantChannelRepository->findOneBy([
                    'merchant' => $merchant,
                    'salesChannel' => $salesChannel,
                ]);
                if ($merchantChannel !== null) {
                    $filters['channelId'] = $merchantChannel->getId();
                }
            }
        }
        if ($query->sku !== null) {
            $filters['search'] = $query->sku;
        }

        $result = $this->listingRepository->findByMerchantPaginated(
            $merchant,
            $query->page,
            $query->pageSize,
            $filters
        );

        return $this->json([
            'success' => true,
            'data' => [
                'items' => array_map(fn (InventoryListing $l) => $this->serializeListing($l), $result['data']),
                'pagination' => [
                    'page' => $query->page,
                    'pageSize' => $query->pageSize,
                    'total' => $result['total'],
                    'totalPages' => (int) ceil($result['total'] / $query->pageSize),
                ],
            ],
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * Get listing details.
     */
    #[Route('/{id}', name: 'details', methods: ['GET'])]
    public function details(string $id, Request $request): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('merchant');

        $listing = $this->listingRepository->find($id);
        if ($listing === null || $listing->getMerchantInventory()->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Listing not found',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $this->serializeListing($listing),
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * Create listing.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[OpenApiOnly(permission: 'listing:write')]
    public function create(
        #[MapRequestPayload] CreateListingRequest $dto,
        Request $request
    ): JsonResponse {
        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('merchant');

        // Find sales channel by code
        $salesChannel = $this->salesChannelRepository->findByCode($dto->channelCode);
        if ($salesChannel === null) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Sales channel not found',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_NOT_FOUND);
        }

        // Find merchant sales channel
        $merchantChannel = $this->merchantChannelRepository->findOneBy([
            'merchant' => $merchant,
            'salesChannel' => $salesChannel,
        ]);
        if ($merchantChannel === null) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Merchant not authorized for this channel',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_FORBIDDEN);
        }

        // Map DTO to internal format
        $internalDto = new InternalCreateRequest();
        $internalDto->merchantInventoryId = $dto->inventoryId;
        $internalDto->merchantSalesChannelId = $merchantChannel->getId();
        $internalDto->fulfillmentType = $dto->fulfillmentType;
        $internalDto->pricingModel = $dto->pricingModel;
        $internalDto->allocationMode = $dto->allocationMode;
        $internalDto->allocatedQuantity = $dto->allocatedQuantity;
        $internalDto->price = $dto->price;
        $internalDto->compareAtPrice = $dto->compareAtPrice;
        $internalDto->remark = $dto->remark;

        try {
            // Create a dummy user for API operations (no actual user in Open API)
            $apiUser = $merchant->getUser();
            $listing = $this->listingService->createListing($merchant, $internalDto, $apiUser);

            return $this->json([
                'success' => true,
                'data' => $this->serializeListing($listing),
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $e->getMessage(),
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Update listing.
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    #[OpenApiOnly(permission: 'listing:write')]
    public function update(
        string $id,
        #[MapRequestPayload] UpdateListingRequest $dto,
        Request $request
    ): JsonResponse {
        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('merchant');

        $listing = $this->listingRepository->find($id);
        if ($listing === null || $listing->getMerchantInventory()->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Listing not found',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_NOT_FOUND);
        }

        // Map DTO to internal format
        $internalDto = new InternalUpdateRequest();
        $internalDto->price = $dto->price;
        $internalDto->compareAtPrice = $dto->compareAtPrice;
        $internalDto->allocatedQuantity = $dto->allocatedQuantity;
        $internalDto->remark = $dto->remark;

        try {
            $apiUser = $merchant->getUser();
            $listing = $this->listingService->updateListing($listing, $internalDto, $apiUser);

            return $this->json([
                'success' => true,
                'data' => $this->serializeListing($listing),
                'requestId' => $request->attributes->get('request_id'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $e->getMessage(),
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete listing (delist).
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[OpenApiOnly(permission: 'listing:write')]
    public function delete(string $id, Request $request): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('merchant');

        $listing = $this->listingRepository->find($id);
        if ($listing === null || $listing->getMerchantInventory()->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Listing not found',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $apiUser = $merchant->getUser();
            $this->listingService->deleteListing($listing, $apiUser);

            return $this->json([
                'success' => true,
                'requestId' => $request->attributes->get('request_id'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $e->getMessage(),
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_BAD_REQUEST);
        }
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
            'sku' => $sku->getSkuName(),
            'productName' => $product->getName(),
            'styleNumber' => $product->getStyleNumber(),
            'color' => $product->getColor(),
            'size' => $sku->getSizeValue(),
            'channelCode' => $salesChannel->getCode(),
            'channelName' => $salesChannel->getName(),
            'warehouseCode' => $inventory->getWarehouse()->getCode(),
            'warehouseName' => $inventory->getWarehouse()->getName(),
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
}
