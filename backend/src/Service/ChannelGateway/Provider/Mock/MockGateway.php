<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Provider\Mock;

use App\Entity\ChannelProduct;
use App\Repository\ChannelProductRepository;
use App\Service\ChannelGateway\AbstractChannelGateway;
use App\Service\ChannelGateway\ChannelGatewayContext;
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
use App\Service\Mock\Dto\MockOrderDto;
use App\Service\Mock\MockOrderStore;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Mock gateway for testing and development.
 *
 * Returns simulated responses without making real API calls.
 * Can be configured to simulate failures via context config.
 *
 * For order pulling:
 * - First checks MockOrderStore for pending orders (created via CLI commands)
 * - Optionally auto-generates orders from active ChannelProduct data
 * - Pulls in batches by pageSize to mimic external API pagination behavior
 */
class MockGateway extends AbstractChannelGateway
{
    private const CHANNEL_CODE = 'MOCK';
    private const CHANNEL_NAME = 'Mock Channel';

    public function __construct(
        LoggerInterface $logger,
        private readonly MockOrderStore $mockOrderStore,
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

    public function pushProduct(
        ChannelGatewayContext $context,
        PushProductRequest $request
    ): PushProductResponse {
        $this->logOperationStart('pushProduct', [
            'productId' => $request->internalId,
            'title' => $request->title,
        ]);
        $this->applySimulatedDelay($context, 'pushProduct');

        if ($this->shouldSimulateFailure($context, 'pushProduct')) {
            $this->handleApiError(500, 'MOCK_ERROR', 'Simulated push product failure');
        }

        $externalId = $request->externalId ?? 'MOCK_'.$request->internalId;

        $this->logOperationSuccess('pushProduct', ['externalId' => $externalId]);

        return new PushProductResponse(
            success: true,
            channelCode: self::CHANNEL_CODE,
            externalId: $externalId,
            externalUrl: 'https://mock-channel.example.com/product/'.$externalId,
            message: 'Product pushed successfully',
            data: [
                'internalId' => $request->internalId,
                'skuCount' => count($request->skus),
                'generatedAt' => $this->createUtcDateTime()->format(\DateTimeInterface::ATOM),
            ],
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
        $this->applySimulatedDelay($context, 'updateStockPrice');

        if ($this->shouldSimulateFailure($context, 'updateStockPrice')) {
            $this->handleApiError(500, 'MOCK_ERROR', 'Simulated stock price update failure');
        }

        $results = [];
        $snapshot = [];
        foreach ($channelProducts as $channelProduct) {
            $results[$channelProduct->getId()] = true;
            $snapshot[] = [
                'channelProductId' => $channelProduct->getId(),
                'externalId' => $channelProduct->getExternalId(),
                'stock' => $channelProduct->getStockQuantity(),
                'price' => $channelProduct->getPlatformPrice(),
            ];
        }

        $this->logOperationSuccess('updateStockPrice', ['updatedCount' => count($results)]);

        return new UpdateStockPriceResponse(
            success: true,
            channelCode: self::CHANNEL_CODE,
            updatedCount: count($results),
            results: $results,
            message: sprintf('%d items updated', count($results)),
            data: [
                'items' => $snapshot,
            ],
            timestamp: $this->createUtcDateTime(),
        );
    }

    public function delistProduct(
        ChannelGatewayContext $context,
        ChannelProduct $channelProduct
    ): ChannelResponse {
        $this->logOperationStart('delistProduct', [
            'channelProductId' => $channelProduct->getId(),
            'externalId' => $channelProduct->getExternalId(),
        ]);
        $this->applySimulatedDelay($context, 'delistProduct');

        if ($this->shouldSimulateFailure($context, 'delistProduct')) {
            $this->handleApiError(500, 'MOCK_ERROR', 'Simulated delist product failure');
        }

        $this->logOperationSuccess('delistProduct', [
            'channelProductId' => $channelProduct->getId(),
        ]);

        return $this->wrapResponse(
            success: true,
            externalId: $channelProduct->getExternalId(),
            message: 'Product delisted successfully',
            data: [
                'channelProductId' => $channelProduct->getId(),
            ],
        );
    }

    public function pullOrders(
        ChannelGatewayContext $context,
        PullOrdersRequest $request
    ): array {
        $this->logOperationStart('pullOrders', [
            'startTime' => $request->startTime->format(\DateTimeInterface::ATOM),
            'endTime' => $request->endTime->format(\DateTimeInterface::ATOM),
            'page' => $request->page,
            'pageSize' => $request->pageSize,
        ]);
        $this->applySimulatedDelay($context, 'pullOrders');

        if ($this->shouldSimulateFailure($context, 'pullOrders')) {
            $this->handleApiError(500, 'MOCK_ERROR', 'Simulated pull orders failure');
        }

        // First, try to get pending orders from store.
        $channelId = $context->getSalesChannel()->getId();
        $storedOrders = $this->mockOrderStore->getPendingOrders($channelId);
        $generatedCount = 0;

        // Optionally auto-generate orders on first page when store is empty.
        if (empty($storedOrders) && $request->page <= 1) {
            $generatedCount = $this->generateMockOrders($context, $request);
            if ($generatedCount > 0) {
                $storedOrders = $this->mockOrderStore->getPendingOrders($channelId);
            }
        }

        if (empty($storedOrders)) {
            $this->logOperationSuccess('pullOrders', [
                'orderCount' => 0,
                'source' => 'empty',
            ]);

            return [];
        }

        // Keep behavior queue-like to work with async next-page messages.
        $filteredOrders = $this->filterPullableOrders($storedOrders, $request);
        $batchSize = max(1, $request->pageSize);
        $batch = array_slice($filteredOrders, 0, $batchSize);

        $orders = [];
        foreach ($batch as $mockOrder) {
            $orders[] = $mockOrder->orderData;
            $this->mockOrderStore->updateStatus($channelId, $mockOrder->mockOrderId, MockOrderDto::STATUS_PULLED);
        }

        $this->logOperationSuccess('pullOrders', [
            'orderCount' => count($orders),
            'source' => $generatedCount > 0 ? 'generated' : 'store',
            'generatedCount' => $generatedCount,
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
        $this->applySimulatedDelay($context, 'confirmOrder');

        if ($this->shouldSimulateFailure($context, 'confirmOrder')) {
            $this->handleApiError(500, 'MOCK_ERROR', 'Simulated confirm order failure');
        }

        // Update mock order status in store.
        $channelId = $context->getSalesChannel()->getId();
        $mockOrder = $this->mockOrderStore->findByExternalOrderId($channelId, $request->externalOrderId);

        if ($mockOrder === null && $this->isStrictOrderCheckEnabled($context)) {
            return $this->wrapResponse(
                success: false,
                externalId: $request->externalOrderId,
                message: 'Mock order not found for confirmation',
                data: [
                    'orderFound' => false,
                ],
            );
        }

        if ($mockOrder !== null) {
            $this->mockOrderStore->updateStatus($channelId, $mockOrder->mockOrderId, MockOrderDto::STATUS_CONFIRMED);
            if ($request->internalOrderId !== null) {
                $this->mockOrderStore->linkToInternalOrder($channelId, $mockOrder->mockOrderId, $request->internalOrderId);
            }
        }

        $this->logOperationSuccess('confirmOrder', ['externalOrderId' => $request->externalOrderId]);

        return $this->wrapResponse(
            success: true,
            externalId: $request->externalOrderId,
            message: 'Order confirmed successfully',
            data: [
                'orderFound' => $mockOrder !== null,
            ],
        );
    }

    public function shipOrder(
        ChannelGatewayContext $context,
        ShipOrderRequest $request
    ): ShipOrderResponse {
        $this->logOperationStart('shipOrder', [
            'externalOrderId' => $request->externalOrderId,
            'trackingNumber' => $request->trackingNumber,
        ]);
        $this->applySimulatedDelay($context, 'shipOrder');

        if ($this->shouldSimulateFailure($context, 'shipOrder')) {
            $this->handleApiError(500, 'MOCK_ERROR', 'Simulated ship order failure');
        }

        // Update mock order status in store.
        $channelId = $context->getSalesChannel()->getId();
        $mockOrder = $this->mockOrderStore->findByExternalOrderId($channelId, $request->externalOrderId);
        if ($mockOrder === null && $this->isStrictOrderCheckEnabled($context)) {
            return new ShipOrderResponse(
                success: false,
                channelCode: self::CHANNEL_CODE,
                externalId: $request->externalOrderId,
                trackingAccepted: false,
                message: 'Mock order not found for shipping',
                data: [
                    'orderFound' => false,
                ],
                timestamp: $this->createUtcDateTime(),
            );
        }

        if ($mockOrder !== null) {
            $this->mockOrderStore->updateStatus($channelId, $mockOrder->mockOrderId, MockOrderDto::STATUS_SHIPPED);
        }

        $this->logOperationSuccess('shipOrder', [
            'externalOrderId' => $request->externalOrderId,
            'trackingNumber' => $request->trackingNumber,
        ]);

        return new ShipOrderResponse(
            success: true,
            channelCode: self::CHANNEL_CODE,
            externalId: $request->externalOrderId,
            trackingAccepted: true,
            message: 'Shipping info pushed successfully',
            data: [
                'orderFound' => $mockOrder !== null,
                'trackingNumber' => $request->trackingNumber,
                'shippingCarrier' => $request->shippingCarrier,
            ],
            timestamp: $this->createUtcDateTime(),
        );
    }

    public function testConnection(ChannelGatewayContext $context): bool
    {
        $this->logOperationStart('testConnection');
        $this->applySimulatedDelay($context, 'testConnection');

        if ($this->shouldSimulateFailure($context, 'testConnection')) {
            $this->logOperationFailure('testConnection', new \RuntimeException('Simulated connection failure'));

            return false;
        }

        $this->logOperationSuccess('testConnection');

        return true;
    }

    /**
     * Check if failure should be simulated for this operation.
     */
    private function shouldSimulateFailure(ChannelGatewayContext $context, string $operation): bool
    {
        // Check global simulate_failure flag.
        if ($context->getConfigValue('simulate_failure', false)) {
            return true;
        }

        // Check operation-specific simulate_failure flag.
        $failOperations = $context->getConfigValue('fail_operations', []);
        if (is_array($failOperations) && in_array($operation, $failOperations, true)) {
            return true;
        }

        $failureRate = (float) $context->getConfigValue('failure_rate', 0.0);
        if ($failureRate > 0.0) {
            $random = random_int(1, 10000) / 10000;
            if ($random <= min(1.0, max(0.0, $failureRate))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param MockOrderDto[] $storedOrders
     *
     * @return MockOrderDto[]
     */
    private function filterPullableOrders(array $storedOrders, PullOrdersRequest $request): array
    {
        $startTs = $request->startTime->getTimestamp();
        $endTs = $request->endTime->getTimestamp();
        $statusFilters = $request->status !== null
            ? array_values(array_filter(array_map(
                static fn (string $status): string => strtolower(trim($status)),
                explode(',', $request->status)
            )))
            : [];

        $filtered = [];
        foreach ($storedOrders as $storedOrder) {
            $placedTs = $storedOrder->orderData->placedAt->getTimestamp();
            if ($placedTs < $startTs || $placedTs > $endTs) {
                continue;
            }

            if (!empty($statusFilters)) {
                $orderStatus = strtolower($storedOrder->orderData->status);
                $paymentStatus = strtolower($storedOrder->orderData->paymentStatus);
                if (!in_array($orderStatus, $statusFilters, true) && !in_array($paymentStatus, $statusFilters, true)) {
                    continue;
                }
            }

            $filtered[] = $storedOrder;
        }

        usort(
            $filtered,
            static fn (MockOrderDto $a, MockOrderDto $b): int => $a->createdAt <=> $b->createdAt
        );

        return $filtered;
    }

    /**
     * Auto-generate pullable mock orders using active channel products.
     */
    private function generateMockOrders(ChannelGatewayContext $context, PullOrdersRequest $request): int
    {
        $orderCount = (int) $context->getConfigValue('mock_order_count', 0);
        if ($orderCount <= 0) {
            return 0;
        }

        $activeProducts = $this->channelProductRepository->findActiveByChannel($context->getSalesChannel());
        $productsWithStock = array_values(array_filter(
            $activeProducts,
            static fn (ChannelProduct $product): bool => $product->getStockQuantity() > 0
        ));

        if (empty($productsWithStock)) {
            $this->logger->warning('[MOCK] No active products with stock found for auto-generated orders', [
                'salesChannelId' => $context->getSalesChannel()->getId(),
            ]);

            return 0;
        }

        $created = 0;
        $channelId = $context->getSalesChannel()->getId();

        for ($i = 0; $i < $orderCount; ++$i) {
            $channelProduct = $productsWithStock[$i % count($productsWithStock)];
            $quantity = $this->resolveOrderQuantity($context, $channelProduct);

            if ($quantity <= 0) {
                continue;
            }

            $placedAt = $this->resolvePlacedAt($request, $i);
            $order = $this->createGeneratedOrder($context, $channelProduct, $quantity, $placedAt, $i);
            $this->mockOrderStore->store($channelId, $order);
            ++$created;
        }

        return $created;
    }

    private function createGeneratedOrder(
        ChannelGatewayContext $context,
        ChannelProduct $channelProduct,
        int $quantity,
        \DateTimeImmutable $placedAt,
        int $index,
    ): MockOrderDto {
        $productSku = $channelProduct->getProductSku();
        $product = $productSku->getProduct();
        $unitPrice = $channelProduct->getPlatformPrice();
        $totalPrice = bcmul($unitPrice, (string) $quantity, 2);

        $mockOrderId = (string) new Ulid();
        $externalOrderId = 'MOCK_'.$channelProduct->getId().'_'.substr($mockOrderId, -8);
        $externalOrderNo = 'MO'.date('YmdHis').str_pad((string) (($index % 999) + 1), 3, '0', STR_PAD_LEFT);

        return new MockOrderDto(
            mockOrderId: $mockOrderId,
            channelProductId: $channelProduct->getId(),
            status: MockOrderDto::STATUS_PENDING,
            fulfillmentType: $this->resolveFulfillmentType($context),
            orderData: new PulledOrderDto(
                externalOrderId: $externalOrderId,
                externalOrderNo: $externalOrderNo,
                status: 'paid',
                paymentStatus: 'paid',
                receiver: new ReceiverDto(
                    name: 'Mock Receiver',
                    phone: '13800138000',
                    address: '123 Mock Street',
                    province: 'Mock Province',
                    city: 'Mock City',
                    district: 'Mock District',
                    postalCode: '100000',
                ),
                totalAmount: $totalPrice,
                productAmount: $totalPrice,
                shippingAmount: '0.00',
                discountAmount: '0.00',
                currency: $context->getCurrency(),
                placedAt: $placedAt,
                paidAt: $placedAt,
                items: [
                    new PulledOrderItemDto(
                        externalProductId: $channelProduct->getExternalId() ?? $channelProduct->getId(),
                        externalSkuId: $productSku->getId(),
                        productName: $product->getName(),
                        productImage: $product->getPrimaryImage()?->getUrl(),
                        quantity: $quantity,
                        unitPrice: $unitPrice,
                        totalPrice: $totalPrice,
                        skuCode: $product->getStyleNumber(),
                        sizeValue: $productSku->getSizeValue(),
                    ),
                ],
                rawData: [
                    'mock' => true,
                    'auto_generated' => true,
                    'channel_product_id' => $channelProduct->getId(),
                    'generated_at' => $this->createUtcDateTime()->format(\DateTimeInterface::ATOM),
                ],
            ),
            createdAt: $this->createUtcDateTime(),
        );
    }

    private function resolveOrderQuantity(ChannelGatewayContext $context, ChannelProduct $channelProduct): int
    {
        $configured = (int) $context->getConfigValue('mock_order_quantity', 1);
        $configured = max(1, $configured);

        return min($configured, max(1, $channelProduct->getStockQuantity()));
    }

    private function resolvePlacedAt(PullOrdersRequest $request, int $index): \DateTimeImmutable
    {
        $start = $request->startTime->getTimestamp();
        $end = $request->endTime->getTimestamp();
        $candidate = $end - (($index + 1) * 60);
        $timestamp = max($start, $candidate);

        return (new \DateTimeImmutable('@'.$timestamp))
            ->setTimezone(new \DateTimeZone('UTC'));
    }

    private function resolveFulfillmentType(ChannelGatewayContext $context): string
    {
        $configured = (string) $context->getConfigValue('mock_fulfillment_type', MockOrderDto::FULFILLMENT_CONSIGNMENT);

        if (in_array($configured, [MockOrderDto::FULFILLMENT_CONSIGNMENT, MockOrderDto::FULFILLMENT_SELF], true)) {
            return $configured;
        }

        return MockOrderDto::FULFILLMENT_CONSIGNMENT;
    }

    private function isStrictOrderCheckEnabled(ChannelGatewayContext $context): bool
    {
        return (bool) $context->getConfigValue('mock_strict_order_check', false);
    }

    private function applySimulatedDelay(ChannelGatewayContext $context, string $operation): void
    {
        $delayMs = (int) $context->getConfigValue('mock_delay_ms', 0);
        $operationDelays = $context->getConfigValue('operation_delays_ms', []);
        if (is_array($operationDelays) && array_key_exists($operation, $operationDelays)) {
            $delayMs = (int) $operationDelays[$operation];
        }

        $delayMs = max(0, min($delayMs, 30000));
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }
}
