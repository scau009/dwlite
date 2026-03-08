<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Provider\KicksCrew;

use App\Entity\ChannelProduct;
use App\Entity\SalesChannel;
use App\Repository\ChannelProductRepository;
use App\Service\ChannelGateway\AbstractChannelGateway;
use App\Service\ChannelGateway\ChannelGatewayContext;
use App\Service\ChannelGateway\ChannelGatewayInterface;
use App\Service\ChannelGateway\Dto\Request\ConfirmOrderRequest;
use App\Service\ChannelGateway\Dto\Request\PullOrdersRequest;
use App\Service\ChannelGateway\Dto\Request\PushProductRequest;
use App\Service\ChannelGateway\Dto\Request\ShipOrderRequest;
use App\Service\ChannelGateway\Dto\Response\ChannelResponse;
use App\Service\ChannelGateway\Dto\Response\PulledOrderDto;
use App\Service\ChannelGateway\Dto\Response\PulledOrderItemDto;
use App\Service\ChannelGateway\Dto\Response\PushProductResponse;
use App\Service\ChannelGateway\Dto\Response\ReceiverDto;
use App\Service\ChannelGateway\Dto\Response\ShipOrderResponse;
use App\Service\ChannelGateway\Dto\Response\UpdateStockPriceResponse;
use App\Service\ChannelGateway\Exception\ChannelApiException;
use Psr\Log\LoggerInterface;

/**
 * Channel gateway implementation for Kicks Crew.
 *
 * Kicks Crew is a sneaker marketplace that uses model_no + size_system + size
 * as SKU identifier. Orders are auto-confirmed after 30 minutes.
 */
class KicksCrewGateway extends AbstractChannelGateway
{
    private const CHANNEL_CODE = 'KICKSCREW';
    private const CHANNEL_NAME = 'Kicks Crew';

    /**
     * Supported operations.
     * Note: CONFIRM_ORDER is not truly supported - KC auto-confirms after 30 minutes.
     *
     * @var string[]
     */
    protected const SUPPORTED_OPERATIONS = [
        ChannelGatewayInterface::OPERATION_UPDATE_STOCK_PRICE,
        ChannelGatewayInterface::OPERATION_DELIST,
        ChannelGatewayInterface::OPERATION_PULL_ORDERS,
        ChannelGatewayInterface::OPERATION_SHIP_ORDER,
    ];

    public function __construct(
        LoggerInterface $logger,
        private readonly KicksCrewApiClient $apiClient,
        private readonly KicksCrewSkuMapper $skuMapper,
        private readonly ChannelProductRepository $channelProductRepository,
    ) {
        parent::__construct($logger);
    }

    public function getChannelCode(): string
    {
        return self::CHANNEL_CODE;
    }

    public function getChannelName(): string
    {
        return self::CHANNEL_NAME;
    }

    /**
     * Push product is not directly supported by KC.
     * Use updateStockPrice instead with model_no + size.
     */
    public function pushProduct(
        ChannelGatewayContext $context,
        PushProductRequest $request
    ): PushProductResponse {
        $this->logOperationStart('pushProduct', [
            'productId' => $request->internalId,
            'title' => $request->title,
        ]);

        // KC doesn't have a separate "create product" API
        // Products are created implicitly when updating stock
        // We'll batch update all SKUs from the request

        $apiKey = $this->getApiKey($context);
        $items = [];

        foreach ($request->skus as $sku) {
            // Extract model_no from attributes or use internalId
            $modelNo = $request->attributes['model_no'] ?? $request->internalId;

            $items[] = $this->skuMapper->toKcListingItem(
                modelNo: $modelNo,
                sizeSystem: $request->attributes['size_system'] ?? 'US',
                size: $sku->sizeValue ?? '',
                qty: $sku->stock,
                price: $this->skuMapper->toKcPrice($sku->price),
                extRef: $this->skuMapper->buildExtRef($sku->internalId),
                brand: $request->brand,
            );
        }

        try {
            $response = $this->apiClient->batchUpdateStock($apiKey, $items);

            $externalId = null;
            if (isset($response['data'][0]['id'])) {
                $externalId = $response['data'][0]['id'];
            }

            $this->logOperationSuccess('pushProduct', [
                'externalId' => $externalId,
                'itemCount' => count($items),
            ]);

            return new PushProductResponse(
                success: ($response['code'] ?? 1) === 0,
                channelCode: self::CHANNEL_CODE,
                externalId: $externalId,
                externalUrl: null, // KC doesn't provide product URL
                message: 'Product listings updated',
                timestamp: $this->createUtcDateTime(),
            );
        } catch (\Throwable $e) {
            $this->logOperationFailure('pushProduct', $e);
            throw $e;
        }
    }

    /**
     * Update stock and price on KC.
     * Uses batch-update API for efficiency.
     *
     * @param ChannelProduct[] $channelProducts
     *
     * @throws \Throwable
     */
    public function updateStockPrice(
        ChannelGatewayContext $context,
        array $channelProducts
    ): UpdateStockPriceResponse {
        $this->logOperationStart('updateStockPrice', [
            'productCount' => count($channelProducts),
        ]);

        $apiKey = $this->getApiKey($context);
        $items = [];
        $results = [];

        foreach ($channelProducts as $channelProduct) {
            $item = $this->buildKcListingItemFromChannelProduct($channelProduct);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        if (empty($items)) {
            $this->logger->info(sprintf('[%s] Nothing to save.', $context->getChannelCode()));

            return new UpdateStockPriceResponse(
                success: true,
                channelCode: self::CHANNEL_CODE,
                updatedCount: 0,
                results: [],
                message: 'No valid items to update',
                timestamp: $this->createUtcDateTime(),
            );
        }

        try {
            $response = $this->apiClient->batchUpdateStock($apiKey, $items);

            // Map results back to channel product IDs
            foreach ($channelProducts as $channelProduct) {
                $results[$channelProduct->getId()] = ($response['code'] ?? 1) === 0;
            }

            $successCount = count(array_filter($results));

            $this->logOperationSuccess('updateStockPrice', [
                'updatedCount' => $successCount,
            ]);

            return new UpdateStockPriceResponse(
                success: $successCount === count($results),
                channelCode: self::CHANNEL_CODE,
                updatedCount: $successCount,
                results: $results,
                message: sprintf('%d of %d items updated', $successCount, count($results)),
                timestamp: $this->createUtcDateTime(),
            );
        } catch (\Throwable $e) {
            $this->logOperationFailure('updateStockPrice', $e);
            throw $e;
        }
    }

    /**
     * Delist (remove) a product from KC.
     * Uses the deleteListings API to remove the listing.
     */
    public function delistProduct(
        ChannelGatewayContext $context,
        ChannelProduct $channelProduct
    ): ChannelResponse {
        $this->logOperationStart('delistProduct', [
            'channelProductId' => $channelProduct->getId(),
            'externalId' => $channelProduct->getExternalId(),
        ]);

        $apiKey = $this->getApiKey($context);

        // Build the listing identifier for KC (model_no + size_system + size)
        $productSku = $channelProduct->getProductSku();
        $product = $productSku->getProduct();
        $modelNo = $product->getStyleNumber();
        $sizeValue = $productSku->getSizeValue();
        $sizeSystem = $this->skuMapper->getSizeSystem($productSku->getSizeUnit());

        if (empty($modelNo) || empty($sizeValue)) {
            $this->logger->warning('[KICKSCREW] Cannot delist: missing model_no or size', [
                'channelProductId' => $channelProduct->getId(),
                'modelNo' => $modelNo,
                'sizeValue' => $sizeValue,
            ]);

            return $this->wrapResponse(
                success: false,
                externalId: $channelProduct->getExternalId(),
                message: 'Missing model_no or size',
            );
        }

        try {
            $response = $this->apiClient->deleteListings($apiKey, [
                'items' => [
                    [
                        'model_no' => $modelNo,
                        'size_system' => $sizeSystem,
                        'size' => $sizeValue,
                    ],
                ],
            ]);

            $success = ($response['code'] ?? 1) === 0;

            if ($success) {
                $this->logOperationSuccess('delistProduct', [
                    'channelProductId' => $channelProduct->getId(),
                ]);
            } else {
                $this->logger->warning('[KICKSCREW] Delist returned non-zero code', [
                    'channelProductId' => $channelProduct->getId(),
                    'response' => $response,
                ]);
            }

            return $this->wrapResponse(
                success: $success,
                externalId: $channelProduct->getExternalId(),
                message: $success ? 'Product delisted successfully' : ($response['message'] ?? 'Delist failed'),
                data: $response,
            );
        } catch (\Throwable $e) {
            $this->logOperationFailure('delistProduct', $e);
            throw $e;
        }
    }

    /**
     * Pull orders from KC.
     *
     * @return PulledOrderDto[]
     *
     * @throws \Throwable
     */
    public function pullOrders(
        ChannelGatewayContext $context,
        PullOrdersRequest $request
    ): array {
        $this->logOperationStart('pullOrders', [
            'startTime' => $request->startTime->format(\DateTimeInterface::ATOM),
            'endTime' => $request->endTime->format(\DateTimeInterface::ATOM),
            'status' => $request->status,
        ]);

        $apiKey = $this->getApiKey($context);
        $salesChannel = $context->getSalesChannel();

        try {
            // KC uses milliseconds for timestamps
            $dateFrom = $request->startTime->getTimestamp() * 1000;
            $dateTo = $request->endTime->getTimestamp() * 1000;

            $response = $this->apiClient->getOrders(
                $apiKey,
                page: $request->page,
                dateFrom: $dateFrom,
                dateTo: $dateTo,
                status: $request->status
            );

            $orders = [];
            foreach ($response['data'] ?? [] as $kcOrder) {
                // Skip on-hold orders
                if ($kcOrder['on_hold'] ?? false) {
                    $this->logger->warning('[KICKSCREW] Skipping on-hold order', [
                        'orderId' => $kcOrder['order_id'] ?? 'unknown',
                    ]);
                    continue;
                }

                $pulledOrder = $this->mapKcOrderToPulledOrder($kcOrder, $salesChannel);
                if ($pulledOrder !== null) {
                    $orders[] = $pulledOrder;
                }
            }

            $this->logOperationSuccess('pullOrders', [
                'orderCount' => count($orders),
            ]);

            return $orders;
        } catch (\Throwable $e) {
            $this->logOperationFailure('pullOrders', $e);
            throw $e;
        }
    }

    /**
     * Confirm order - KC auto-confirms after 30 minutes.
     * This method just returns success without making API call.
     */
    public function confirmOrder(
        ChannelGatewayContext $context,
        ConfirmOrderRequest $request
    ): ChannelResponse {
        $this->logOperationStart('confirmOrder', [
            'externalOrderId' => $request->externalOrderId,
        ]);

        // KC auto-confirms orders after 30 minutes
        // No explicit confirmation API needed
        $this->logger->info('[KICKSCREW] Order auto-confirmed by KC after 30 minutes', [
            'externalOrderId' => $request->externalOrderId,
        ]);

        return $this->wrapResponse(
            success: true,
            externalId: $request->externalOrderId,
            message: 'KC auto-confirms orders after 30 minutes',
        );
    }

    /**
     * Ship order to KC warehouse.
     */
    public function shipOrder(
        ChannelGatewayContext $context,
        ShipOrderRequest $request
    ): ShipOrderResponse {
        $this->logOperationStart('shipOrder', [
            'externalOrderId' => $request->externalOrderId,
            'trackingNumber' => $request->trackingNumber,
            'carrier' => $request->shippingCarrier,
        ]);

        $apiKey = $this->getApiKey($context);

        try {
            // Map carrier name to KC courier_slug
            $courierSlug = $this->mapCarrierToCourierSlug($request->shippingCarrier);

            $response = $this->apiClient->confirmShipped(
                $apiKey,
                $request->externalOrderId,
                $courierSlug,
                $request->trackingNumber
            );

            $success = ($response['code'] ?? 1) === 0;

            $this->logOperationSuccess('shipOrder', [
                'externalOrderId' => $request->externalOrderId,
                'success' => $success,
            ]);

            return new ShipOrderResponse(
                success: $success,
                channelCode: self::CHANNEL_CODE,
                externalId: $request->externalOrderId,
                trackingAccepted: $success,
                message: $response['message'] ?? ($success ? 'Shipped successfully' : 'Ship failed'),
                timestamp: $this->createUtcDateTime(),
            );
        } catch (\Throwable $e) {
            $this->logOperationFailure('shipOrder', $e);
            throw $e;
        }
    }

    /**
     * Get the API client instance.
     *
     * This is exposed for use by console commands that need direct API access.
     */
    public function getApiClient(): KicksCrewApiClient
    {
        return $this->apiClient;
    }

    /**
     * Get API key from context.
     *
     * @throws ChannelApiException
     */
    private function getApiKey(ChannelGatewayContext $context): string
    {
        $apiKey = $context->getConfigValue('api_key');

        if (empty($apiKey)) {
            throw new ChannelApiException('[KICKSCREW] API key not configured', 'CONFIG_ERROR', 400);
        }

        return $apiKey;
    }

    /**
     * Build KC listing item from ChannelProduct entity.
     *
     * Extracts model_no, size_system, and size from the related ProductSku and Product entities.
     *
     * @return array<string, mixed>|null
     */
    private function buildKcListingItemFromChannelProduct(ChannelProduct $channelProduct): ?array
    {
        $productSku = $channelProduct->getProductSku();
        $product = $productSku->getProduct();

        $modelNo = $product->getStyleNumber();
        $sizeValue = $productSku->getSizeValue();
        $sizeSystem = $this->skuMapper->getSizeSystem($productSku->getSizeUnit());

        if (empty($modelNo) || empty($sizeValue)) {
            $this->logger->warning('[KICKSCREW] Missing model_no or size', [
                'channelProductId' => $channelProduct->getId(),
                'modelNo' => $modelNo,
                'sizeValue' => $sizeValue,
            ]);

            return null;
        }

        return $this->skuMapper->toKcListingItem(
            modelNo: $modelNo,
            sizeSystem: $sizeSystem,
            size: $sizeValue,
            qty: $channelProduct->getStockQuantity(),
            price: $this->skuMapper->toKcPrice($channelProduct->getPlatformPrice()),
            extRef: $this->skuMapper->buildExtRef($channelProduct->getId()),
            brand: $product->getBrand()?->getName(),
        );
    }

    /**
     * Map KC order to PulledOrderDto.
     *
     * This method is public to allow direct order mapping from console commands.
     */
    public function mapKcOrderToPulledOrder(array $kcOrder, SalesChannel $salesChannel): ?PulledOrderDto
    {
        try {
            $status = $this->mapKcStatusToSystemStatus($kcOrder['status'] ?? '');
            $paymentStatus = 'paid'; // KC orders are pre-paid

            // Parse size object to get size system and value
            $sizeObject = $kcOrder['size'] ?? [];
            $parsedSize = $this->skuMapper->parseOrderSize($sizeObject);
            $sizeSystem = $parsedSize['sizeSystem'];
            $sizeValue = $parsedSize['sizeValue'];

            // Format size value for display (e.g., "US 9")
            $sizeDisplay = $sizeSystem !== '' ? sprintf('%s %s', $sizeSystem, $sizeValue) : '';

            // Find ChannelProduct by model_no and size to get externalId
            $modelNo = $kcOrder['model_no'] ?? '';
            $externalProductId = $this->findChannelProductExternalId($salesChannel, $modelNo, $sizeSystem, $sizeValue);

            $receiver = new ReceiverDto(
                name: $kcOrder['recipient_name'] ?? '',
                phone: $kcOrder['mobile'] ?? '',
                address: trim(($kcOrder['address_line1'] ?? '').' '.($kcOrder['address_line2'] ?? '')),
                province: $kcOrder['state_province'] ?? null,
                city: $kcOrder['city'] ?? null,
                postalCode: $kcOrder['zip'] ?? null,
            );

            // Create order item
            $item = new PulledOrderItemDto(
                externalProductId: $externalProductId,
                externalSkuId: $kcOrder['stock_id'] ?? null,
                productName: sprintf('%s %s', $kcOrder['brand'] ?? '', $kcOrder['model_no'] ?? ''),
                productImage: null,
                quantity: 1, // KC orders are single item
                unitPrice: (string) ($kcOrder['price'] ?? 0),
                totalPrice: (string) ($kcOrder['price'] ?? 0),
                skuCode: $kcOrder['model_no'] ?? null,
                sizeValue: $sizeDisplay,
            );

            $placedAt = isset($kcOrder['created_at'])
                ? new \DateTimeImmutable($kcOrder['created_at'], new \DateTimeZone('UTC'))
                : $this->createUtcDateTime();

            return new PulledOrderDto(
                externalOrderId: (string) ($kcOrder['order_id'] ?? ''),
                externalOrderNo: $kcOrder['order_number'] ?? null,
                status: $status,
                paymentStatus: $paymentStatus,
                receiver: $receiver,
                totalAmount: (string) ($kcOrder['price'] ?? 0),
                productAmount: (string) ($kcOrder['price'] ?? 0),
                shippingAmount: '0.00',
                discountAmount: '0.00',
                currency: $kcOrder['currency'] ?? 'USD',
                placedAt: $placedAt,
                paidAt: $placedAt, // KC orders are pre-paid
                items: [$item],
                buyerRemark: $kcOrder['customer_order_reference'] ?? null,
                rawData: $kcOrder,
            );
        } catch (\Throwable $e) {
            $this->logger->error('[KICKSCREW] Failed to map order', [
                'orderId' => $kcOrder['order_id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Find ChannelProduct external_id by model_no and size.
     */
    private function findChannelProductExternalId(
        SalesChannel $salesChannel,
        string $modelNo,
        string $sizeSystem,
        string $sizeValue,
    ): string {
        if (empty($modelNo) || empty($sizeSystem) || empty($sizeValue)) {
            return $modelNo;
        }

        $channelProduct = $this->channelProductRepository->findByStyleNumberAndSize(
            $salesChannel,
            $modelNo,
            $sizeSystem,
            $sizeValue
        );

        if ($channelProduct !== null && $channelProduct->getExternalId() !== null) {
            return $channelProduct->getExternalId();
        }

        // Fallback to model_no if no ChannelProduct found
        $this->logger->warning('[KICKSCREW] ChannelProduct not found for order item', [
            'modelNo' => $modelNo,
            'sizeSystem' => $sizeSystem,
            'sizeValue' => $sizeValue,
        ]);

        return $modelNo;
    }

    /**
     * Map KC status to system status.
     */
    private function mapKcStatusToSystemStatus(string $kcStatus): string
    {
        return match ($kcStatus) {
            'order.confirmed' => 'pending',      // Ready to ship
            'order.packed' => 'processing',      // Packed, waiting pickup
            'seller.shipped' => 'shipped',       // On way to KC
            'order.shipped' => 'shipped',        // KC shipped to customer
            'order.completed' => 'completed',
            'order.canceled' => 'cancelled',
            default => 'pending',
        };
    }

    /**
     * Map carrier name to KC courier_slug.
     */
    private function mapCarrierToCourierSlug(string $carrier): string
    {
        $mapping = [
            'FedEx' => 'fedex',
            'UPS' => 'ups',
            'DHL' => 'dhl',
            'USPS' => 'usps',
            'SF Express' => 'sf-express',
            'SF' => 'sf-express',
            '顺丰' => 'sf-express',
            'EMS' => 'ems',
            'China Post' => 'china-post',
        ];

        // Case-insensitive lookup
        foreach ($mapping as $name => $slug) {
            if (strcasecmp($carrier, $name) === 0) {
                return $slug;
            }
        }

        // Default: lowercase and replace spaces
        return strtolower(str_replace(' ', '-', $carrier));
    }
}
