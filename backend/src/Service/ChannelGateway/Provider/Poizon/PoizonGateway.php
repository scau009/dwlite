<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Provider\Poizon;

use App\Entity\ChannelProduct;
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
 * Channel gateway implementation for Poizon (得物).
 *
 * MVP scope: authentication only. Business operations are stubbed until
 * full API documentation is available.
 */
class PoizonGateway extends AbstractChannelGateway
{
    private const CHANNEL_CODE = 'POIZON';
    private const CHANNEL_NAME = 'Poizon (得物)';

    /** @var string[] */
    protected const SUPPORTED_OPERATIONS = [
        ChannelGatewayInterface::OPERATION_PUSH_PRODUCT,
        ChannelGatewayInterface::OPERATION_UPDATE_STOCK_PRICE,
        ChannelGatewayInterface::OPERATION_DELIST,
        ChannelGatewayInterface::OPERATION_PULL_ORDERS,
        ChannelGatewayInterface::OPERATION_CONFIRM_ORDER,
        ChannelGatewayInterface::OPERATION_SHIP_ORDER,
    ];

    public function __construct(
        LoggerInterface $logger,
        private readonly PoizonApiClient $apiClient,
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
     * Test connection by calling a lightweight API endpoint.
     *
     * Queries a known brand ID; returns true if the call does not raise
     * an authentication error.
     */
    public function testConnection(ChannelGatewayContext $context): bool
    {
        $this->logOperationStart('testConnection');

        $appKey = $this->getAppKey($context);
        $appSecret = $this->getAppSecret($context);

        try {
            $this->apiClient->getBrandsByIds($appKey, $appSecret, [1]);
            $this->logOperationSuccess('testConnection');

            return true;
        } catch (\Throwable $e) {
            $this->logOperationFailure('testConnection', $e);

            return false;
        }
    }

    public function pushProduct(
        ChannelGatewayContext $context,
        PushProductRequest $request
    ): PushProductResponse {
        $this->logOperationStart('pushProduct', [
            'skuCount' => count($request->skus),
        ]);

        $appKey = $this->getAppKey($context);
        $appSecret = $this->getAppSecret($context);

        $countryCode = (string) $context->getConfigValue('country_code', '');
        if ($countryCode === '') {
            throw new ChannelApiException('[POIZON] country_code not configured', 'CONFIG_ERROR', 400);
        }

        $currency = $context->getCurrency();
        $language = (string) $context->getConfigValue('language', 'en');
        $timeZone = (string) $context->getConfigValue('timezone', 'Asia/Shanghai');

        $data = [];
        $firstSellerBiddingNo = null;

        foreach ($request->skus as $sku) {
            if (empty($sku->externalId)) {
                $this->logger->warning('[POIZON] Skipping SKU: externalId (globalSkuId) not set', [
                    'skuInternalId' => $sku->internalId,
                ]);
                continue;
            }

            $globalSkuId = (int) $sku->externalId;
            $price = (int) round((float) $sku->price * 100);
            $quantity = $sku->stock;
            $requestId = bin2hex(random_bytes(16));

            try {
                $response = $this->apiClient->manualListing(
                    appKey: $appKey,
                    appSecret: $appSecret,
                    requestId: $requestId,
                    globalSkuId: $globalSkuId,
                    price: $price,
                    quantity: $quantity,
                    countryCode: $countryCode,
                    deliveryCountryCode: $countryCode,
                    currency: $currency,
                    language: $language,
                    timeZone: $timeZone,
                );

                $sellerBiddingNo = $response['data']['sellerBiddingNo'] ?? null;

                if ($sellerBiddingNo !== null) {
                    $data[$sku->internalId] = $sellerBiddingNo;

                    if ($firstSellerBiddingNo === null) {
                        $firstSellerBiddingNo = (string) $sellerBiddingNo;
                    }
                }
            } catch (\Throwable $e) {
                $this->logOperationFailure('pushProduct', $e, [
                    'skuInternalId' => $sku->internalId,
                    'globalSkuId' => $globalSkuId,
                ]);
            }
        }

        $success = $data !== [];

        $this->logOperationSuccess('pushProduct', [
            'listedCount' => count($data),
        ]);

        return new PushProductResponse(
            success: $success,
            channelCode: self::CHANNEL_CODE,
            externalId: $firstSellerBiddingNo,
            data: $data !== [] ? $data : null,
            timestamp: $this->createUtcDateTime(),
        );
    }

    /**
     * @param ChannelProduct[] $channelProducts
     */
    public function updateStockPrice(
        ChannelGatewayContext $context,
        array $channelProducts
    ): UpdateStockPriceResponse {
        $this->logOperationStart('updateStockPrice', [
            'productCount' => count($channelProducts),
        ]);

        $appKey = $this->getAppKey($context);
        $appSecret = $this->getAppSecret($context);

        $countryCode = (string) $context->getConfigValue('country_code', '');
        if ($countryCode === '') {
            throw new ChannelApiException('[POIZON] country_code not configured', 'CONFIG_ERROR', 400);
        }

        $currency = $context->getCurrency();
        $language = (string) $context->getConfigValue('language', 'en');
        $timeZone = (string) $context->getConfigValue('timezone', 'Asia/Shanghai');

        $results = [];
        $data = [];

        foreach ($channelProducts as $channelProduct) {
            $externalId = $channelProduct->getExternalId();

            if (empty($externalId)) {
                $this->logger->warning('[POIZON] Skipping product: externalId (sellerBiddingNo) not set', [
                    'channelProductId' => $channelProduct->getId(),
                ]);
                $results[$channelProduct->getId()] = false;
                continue;
            }

            $price = (int) round((float) $channelProduct->getPlatformPrice() * 100);
            $quantity = $channelProduct->getEffectiveStock();
            $requestId = bin2hex(random_bytes(16));

            try {
                $response = $this->apiClient->updateManualListing(
                    appKey: $appKey,
                    appSecret: $appSecret,
                    requestId: $requestId,
                    sellerBiddingNo: $externalId,
                    globalSkuId: null,
                    price: $price,
                    quantity: $quantity,
                    oldQuantity: $quantity,
                    countryCode: $countryCode,
                    deliveryCountryCode: $countryCode,
                    currency: $currency,
                    language: $language,
                    timeZone: $timeZone,
                );

                $results[$channelProduct->getId()] = true;

                $responseData = $response['data'] ?? null;
                if ($responseData !== null) {
                    $data[$channelProduct->getId()] = $responseData;
                }
            } catch (\Throwable $e) {
                $this->logOperationFailure('updateStockPrice', $e, [
                    'channelProductId' => $channelProduct->getId(),
                    'sellerBiddingNo' => $externalId,
                ]);
                $results[$channelProduct->getId()] = false;
            }
        }

        $successCount = count(array_filter($results));

        $this->logOperationSuccess('updateStockPrice', [
            'updatedCount' => $successCount,
            'totalCount' => count($results),
        ]);

        return new UpdateStockPriceResponse(
            success: $successCount === count($results) && count($results) > 0,
            channelCode: self::CHANNEL_CODE,
            updatedCount: $successCount,
            results: $results,
            message: sprintf('%d of %d items updated', $successCount, count($results)),
            data: $data !== [] ? $data : null,
            timestamp: $this->createUtcDateTime(),
        );
    }

    public function delistProduct(
        ChannelGatewayContext $context,
        ChannelProduct $channelProduct
    ): ChannelResponse {
        $this->logOperationStart('delistProduct', [
            'channelProductId' => $channelProduct->getId(),
        ]);

        $appKey = $this->getAppKey($context);
        $appSecret = $this->getAppSecret($context);
        $language = (string) $context->getConfigValue('language', 'en');
        $timeZone = (string) $context->getConfigValue('timezone', 'Asia/Shanghai');

        $sellerBiddingNo = $channelProduct->getExternalId();
        if (empty($sellerBiddingNo)) {
            throw new ChannelApiException('[POIZON] externalId (sellerBiddingNo) not set', 'CONFIG_ERROR', 400);
        }

        $response = $this->apiClient->cancelListing(
            appKey: $appKey,
            appSecret: $appSecret,
            sellerBiddingNo: $sellerBiddingNo,
            language: $language,
            timeZone: $timeZone,
        );

        $success = ($response['data'] ?? false) === true;

        $this->logOperationSuccess('delistProduct', [
            'channelProductId' => $channelProduct->getId(),
            'sellerBiddingNo' => $sellerBiddingNo,
            'success' => $success,
        ]);

        return new ChannelResponse(
            success: $success,
            channelCode: self::CHANNEL_CODE,
            timestamp: $this->createUtcDateTime(),
        );
    }

    /**
     * @return PulledOrderDto[]
     */
    public function pullOrders(
        ChannelGatewayContext $context,
        PullOrdersRequest $request
    ): array {
        $this->logOperationStart('pullOrders', [
            'startTime' => $request->startTime->format('Y-m-d H:i:s'),
            'endTime' => $request->endTime->format('Y-m-d H:i:s'),
            'page' => $request->page,
        ]);

        $appKey = $this->getAppKey($context);
        $appSecret = $this->getAppSecret($context);
        $language = (string) $context->getConfigValue('language', 'en');
        $timeZone = (string) $context->getConfigValue('timezone', 'Asia/Shanghai');

        $response = $this->apiClient->queryOrders(
            appKey: $appKey,
            appSecret: $appSecret,
            orderStatus: $request->status !== null ? (int) $request->status : null,
            startCreated: $request->startTime->format('Y-m-d H:i:s'),
            endCreated: $request->endTime->format('Y-m-d H:i:s'),
            pageNo: $request->page,
            pageSize: $request->pageSize,
            language: $language,
            timeZone: $timeZone,
        );

        $orders = [];
        $rawOrders = $response['data']['orders'] ?? [];

        foreach ($rawOrders as $rawOrder) {
            try {
                $orders[] = $this->mapOrderFromPoizon($rawOrder, $context);
            } catch (\Throwable $e) {
                $this->logger->warning('[POIZON] Failed to map order', [
                    'orderNo' => $rawOrder['order_no'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logOperationSuccess('pullOrders', [
            'orderCount' => count($orders),
            'totalResults' => $response['data']['total_results'] ?? 0,
        ]);

        return $orders;
    }

    public function confirmOrder(
        ChannelGatewayContext $context,
        ConfirmOrderRequest $request
    ): ChannelResponse {
        $this->logOperationStart('confirmOrder', [
            'externalOrderId' => $request->externalOrderId,
        ]);

        $appKey = $this->getAppKey($context);
        $appSecret = $this->getAppSecret($context);
        $language = (string) $context->getConfigValue('language', 'en');
        $timeZone = (string) $context->getConfigValue('timezone', 'Asia/Shanghai');

        $response = $this->apiClient->confirmOrder(
            appKey: $appKey,
            appSecret: $appSecret,
            orderItemNo: $request->externalOrderId,
            language: $language,
            timeZone: $timeZone,
        );

        $success = ($response['data']['confirmResult'] ?? false) === true;
        $message = $success ? null : ($response['data']['failureMessage'] ?? null);

        $this->logOperationSuccess('confirmOrder', [
            'externalOrderId' => $request->externalOrderId,
            'success' => $success,
        ]);

        return new ChannelResponse(
            success: $success,
            channelCode: self::CHANNEL_CODE,
            message: $message,
            timestamp: $this->createUtcDateTime(),
        );
    }

    public function shipOrder(
        ChannelGatewayContext $context,
        ShipOrderRequest $request
    ): ShipOrderResponse {
        $this->logOperationStart('shipOrder', [
            'externalOrderId' => $request->externalOrderId,
        ]);

        $appKey = $this->getAppKey($context);
        $appSecret = $this->getAppSecret($context);
        $language = (string) $context->getConfigValue('language', 'en');
        $timeZone = (string) $context->getConfigValue('timezone', 'Asia/Shanghai');

        $deliveryRegion = (string) $context->getConfigValue('delivery_region', '');
        if ($deliveryRegion === '') {
            throw new ChannelApiException('[POIZON] delivery_region not configured', 'CONFIG_ERROR', 400);
        }

        $deliveryType = (string) $context->getConfigValue('delivery_type', 'OFFLINE_EXPRESS_DELIVERY');

        $carrier = $this->resolveCarrierCode($request->shippingCarrierCode, $request->shippingCarrier);

        $response = $this->apiClient->shipOrder(
            appKey: $appKey,
            appSecret: $appSecret,
            orderNoList: [$request->externalOrderId],
            carrier: $carrier,
            deliveryRegion: $deliveryRegion,
            deliveryType: $deliveryType,
            expressNo: $request->trackingNumber !== '' ? $request->trackingNumber : null,
            language: $language,
            timeZone: $timeZone,
        );

        $responseData = $response['data'] ?? [];
        $successList = $responseData['success_order_no_list'] ?? [];
        $failedList = $responseData['failed_item_list'] ?? [];
        $deliveryNo = $responseData['delivery_no'] ?? null;

        $trackingAccepted = in_array($request->externalOrderId, $successList, true);
        $success = $trackingAccepted && $failedList === [];

        $message = null;
        if (!empty($failedList)) {
            $message = (string) ($failedList[0]['failedMsg'] ?? 'Ship order failed');
        }

        $this->logOperationSuccess('shipOrder', [
            'externalOrderId' => $request->externalOrderId,
            'trackingAccepted' => $trackingAccepted,
            'deliveryNo' => $deliveryNo,
        ]);

        return new ShipOrderResponse(
            success: $success,
            channelCode: self::CHANNEL_CODE,
            externalId: $deliveryNo !== null ? (string) $deliveryNo : null,
            trackingAccepted: $trackingAccepted,
            message: $message,
            data: $responseData !== [] ? $responseData : null,
            timestamp: $this->createUtcDateTime(),
        );
    }

    /**
     * Resolve Poizon carrier integer code.
     *
     * Uses shippingCarrierCode if it's a numeric string (Poizon ID already resolved),
     * otherwise maps well-known carrier names to their Poizon codes.
     * Falls back to 100 (Self-delivery) if unknown.
     */
    private function resolveCarrierCode(?string $carrierCode, string $carrierName): int
    {
        if ($carrierCode !== null && ctype_digit($carrierCode)) {
            return (int) $carrierCode;
        }

        return match (strtoupper($carrierName)) {
            'UPS' => 7,
            'FEDEX' => 8,
            'USPS' => 9,
            'DHL' => 22,
            'SAGAWA' => 11,
            'YAMATO' => 10,
            default => 100, // Self-delivery
        };
    }

    /**
     * @throws ChannelApiException
     */
    private function getAppKey(ChannelGatewayContext $context): string
    {
        $appKey = $context->getConfigValue('app_key');

        if (empty($appKey)) {
            throw new ChannelApiException('[POIZON] app_key not configured', 'CONFIG_ERROR', 400);
        }

        return (string) $appKey;
    }

    /**
     * @throws ChannelApiException
     */
    private function getAppSecret(ChannelGatewayContext $context): string
    {
        $appSecret = $context->getConfigValue('app_secret');

        if (empty($appSecret)) {
            throw new ChannelApiException('[POIZON] app_secret not configured', 'CONFIG_ERROR', 400);
        }

        return (string) $appSecret;
    }

    /**
     * Map Poizon order data to PulledOrderDto.
     *
     * @param array<string, mixed> $rawOrder
     */
    private function mapOrderFromPoizon(array $rawOrder, ChannelGatewayContext $context): PulledOrderDto
    {
        $currency = $rawOrder['currency'] ?? $context->getCurrency();

        // Map receiver address
        $deliveryAddress = $rawOrder['delivery_address_platform'] ?? [];
        $receiver = new ReceiverDto(
            name: (string) ($deliveryAddress['name'] ?? ''),
            phone: (string) ($deliveryAddress['mobile'] ?? ''),
            address: (string) ($deliveryAddress['address_detail'] ?? ''),
            province: $deliveryAddress['province'] ?? null,
            city: $deliveryAddress['city'] ?? null,
            district: $deliveryAddress['district'] ?? null,
            postalCode: $deliveryAddress['postcode'] ?? null,
        );

        // Map order items
        $items = [];
        $item = new PulledOrderItemDto(
            externalProductId: (string) ($rawOrder['spu_id'] ?? ''),
            externalSkuId: isset($rawOrder['sku_id']) ? (string) $rawOrder['sku_id'] : null,
            productName: (string) ($rawOrder['title'] ?? ''),
            productImage: $rawOrder['logo_url'] ?? null,
            quantity: (int) ($rawOrder['qty'] ?? 1),
            unitPrice: $this->formatPrice($rawOrder['sku_price'] ?? 0, $currency),
            totalPrice: $this->formatPrice($rawOrder['amount'] ?? 0, $currency),
            skuCode: $rawOrder['article_number'] ?? null,
            sizeValue: $rawOrder['properties'] ?? null,
            attributes: [
                'seller_bidding_no' => $rawOrder['seller_bidding_no'] ?? null,
                'inventory_no' => $rawOrder['inventory_no'] ?? null,
                'brand_id' => $rawOrder['brand_id'] ?? null,
            ],
        );
        $items[] = $item;

        // Parse timestamps
        $placedAt = $this->parsePoizonDateTime($rawOrder['create_time'] ?? null);
        $paidAt = $this->parsePoizonDateTime($rawOrder['pay_time'] ?? null);

        // Calculate amounts
        $totalAmount = $this->formatPrice($rawOrder['amount'] ?? 0, $currency);
        $productAmount = $this->formatPrice($rawOrder['pay_amount'] ?? 0, $currency);
        $shippingAmount = $this->formatPrice($rawOrder['trade_freight_info']['freight'] ?? 0, $currency);
        $poundage = $rawOrder['poundage'] ?? 0;
        $discountAmount = $this->formatPrice(max(0, ($rawOrder['amount'] ?? 0) - ($rawOrder['pay_amount'] ?? 0) - $poundage), $currency);

        return new PulledOrderDto(
            externalOrderId: (string) ($rawOrder['order_no'] ?? ''),
            externalOrderNo: (string) ($rawOrder['order_no'] ?? ''),
            status: $this->mapOrderStatus($rawOrder['order_status'] ?? 0),
            paymentStatus: $this->mapPaymentStatus($rawOrder['pay_status'] ?? 0),
            receiver: $receiver,
            totalAmount: $totalAmount,
            productAmount: $productAmount,
            shippingAmount: $shippingAmount,
            discountAmount: $discountAmount,
            currency: $currency,
            placedAt: $placedAt,
            paidAt: $paidAt,
            items: $items,
            buyerRemark: null,
            rawData: $rawOrder,
        );
    }

    /**
     * Format price from smallest unit (cents) to decimal string.
     */
    private function formatPrice(int|float $amount, string $currency): string
    {
        return number_format((float) $amount / 100, 2, '.', '');
    }

    /**
     * Parse Poizon datetime string to DateTimeImmutable.
     */
    private function parsePoizonDateTime(?string $datetime): ?\DateTimeImmutable
    {
        if ($datetime === null || $datetime === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($datetime, new \DateTimeZone('UTC'));
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Map Poizon order status to internal status.
     */
    private function mapOrderStatus(int $status): string
    {
        return match ($status) {
            1000 => 'pending_payment',
            2000 => 'paid',
            2100 => 'seller_shipped',
            2200 => 'platform_received',
            2500 => 'authenticated',
            2550 => 'awaiting_platform_shipment',
            2600 => 'platform_shipped',
            2650 => 'in_transit',
            2700 => 'logistics_collected',
            2800 => 'delivered',
            3040 => 'awaiting_buyer_receipt',
            4000 => 'completed',
            7000 => 'payment_failed',
            8000 => 'closed',
            8010 => 'closed_no_refund',
            8080 => 'refunded',
            default => 'unknown',
        };
    }

    /**
     * Map Poizon payment status to internal status.
     */
    private function mapPaymentStatus(int $status): string
    {
        return match ($status) {
            0 => 'unpaid',
            2 => 'paid',
            default => 'unknown',
        };
    }
}
