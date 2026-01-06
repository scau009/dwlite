<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Provider\Mock;

use App\Service\ChannelGateway\AbstractChannelGateway;
use App\Service\ChannelGateway\ChannelGatewayContext;
use App\Service\ChannelGateway\Dto\Request\ConfirmOrderRequest;
use App\Service\ChannelGateway\Dto\Request\PullOrdersRequest;
use App\Service\ChannelGateway\Dto\Request\PushProductRequest;
use App\Service\ChannelGateway\Dto\Request\ShipOrderRequest;
use App\Service\ChannelGateway\Dto\Request\UpdateStockPriceRequest;
use App\Service\ChannelGateway\Dto\Response\ChannelResponse;
use App\Service\ChannelGateway\Dto\Response\PulledOrderDto;
use App\Service\ChannelGateway\Dto\Response\PulledOrderItemDto;
use App\Service\ChannelGateway\Dto\Response\PushProductResponse;
use App\Service\ChannelGateway\Dto\Response\ReceiverDto;
use App\Service\ChannelGateway\Dto\Response\ShipOrderResponse;
use App\Service\ChannelGateway\Dto\Response\UpdateStockPriceResponse;
use Psr\Log\LoggerInterface;

/**
 * Mock gateway for testing and development.
 *
 * Returns simulated responses without making real API calls.
 * Can be configured to simulate failures via context config.
 */
class MockGateway extends AbstractChannelGateway
{
    private const CHANNEL_CODE = 'MOCK';
    private const CHANNEL_NAME = 'Mock Channel';

    public function __construct(
        LoggerInterface $logger,
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

        if ($this->shouldSimulateFailure($context, 'pushProduct')) {
            $this->handleApiError(500, 'MOCK_ERROR', 'Simulated push product failure');
        }

        $externalId = 'MOCK_'.$request->internalId.'_'.time();

        $this->logOperationSuccess('pushProduct', ['externalId' => $externalId]);

        return new PushProductResponse(
            success: true,
            channelCode: self::CHANNEL_CODE,
            externalId: $externalId,
            externalUrl: 'https://mock-channel.example.com/product/'.$externalId,
            message: 'Product pushed successfully',
            timestamp: $this->createUtcDateTime(),
        );
    }

    public function updateStockPrice(
        ChannelGatewayContext $context,
        UpdateStockPriceRequest $request
    ): UpdateStockPriceResponse {
        $this->logOperationStart('updateStockPrice', [
            'updates' => count($request->items),
        ]);

        if ($this->shouldSimulateFailure($context, 'updateStockPrice')) {
            $this->handleApiError(500, 'MOCK_ERROR', 'Simulated stock price update failure');
        }

        $results = [];
        foreach ($request->items as $update) {
            $results[$update->externalId] = true;
        }

        $this->logOperationSuccess('updateStockPrice', ['updatedCount' => count($results)]);

        return new UpdateStockPriceResponse(
            success: true,
            channelCode: self::CHANNEL_CODE,
            updatedCount: count($results),
            results: $results,
            message: sprintf('%d items updated', count($results)),
            timestamp: $this->createUtcDateTime(),
        );
    }

    public function pullOrders(
        ChannelGatewayContext $context,
        PullOrdersRequest $request
    ): array {
        $this->logOperationStart('pullOrders', [
            'startTime' => $request->startTime->format(\DateTimeInterface::ATOM),
            'endTime' => $request->endTime->format(\DateTimeInterface::ATOM),
        ]);

        if ($this->shouldSimulateFailure($context, 'pullOrders')) {
            $this->handleApiError(500, 'MOCK_ERROR', 'Simulated pull orders failure');
        }

        // Generate mock orders
        $orders = $this->generateMockOrders($context);

        $this->logOperationSuccess('pullOrders', ['orderCount' => count($orders)]);

        return $orders;
    }

    public function confirmOrder(
        ChannelGatewayContext $context,
        ConfirmOrderRequest $request
    ): ChannelResponse {
        $this->logOperationStart('confirmOrder', [
            'externalOrderId' => $request->externalOrderId,
        ]);

        if ($this->shouldSimulateFailure($context, 'confirmOrder')) {
            $this->handleApiError(500, 'MOCK_ERROR', 'Simulated confirm order failure');
        }

        $this->logOperationSuccess('confirmOrder', ['externalOrderId' => $request->externalOrderId]);

        return $this->wrapResponse(
            success: true,
            externalId: $request->externalOrderId,
            message: 'Order confirmed successfully',
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

        if ($this->shouldSimulateFailure($context, 'shipOrder')) {
            $this->handleApiError(500, 'MOCK_ERROR', 'Simulated ship order failure');
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
            timestamp: $this->createUtcDateTime(),
        );
    }

    public function testConnection(ChannelGatewayContext $context): bool
    {
        $this->logOperationStart('testConnection');

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
        // Check global simulate_failure flag
        if ($context->getConfigValue('simulate_failure', false)) {
            return true;
        }

        // Check operation-specific simulate_failure flag
        $failOperations = $context->getConfigValue('fail_operations', []);
        if (is_array($failOperations) && in_array($operation, $failOperations, true)) {
            return true;
        }

        return false;
    }

    /**
     * Generate mock orders for testing.
     *
     * @return PulledOrderDto[]
     */
    private function generateMockOrders(ChannelGatewayContext $context): array
    {
        $orderCount = (int) $context->getConfigValue('mock_order_count', 2);

        $orders = [];
        for ($i = 1; $i <= $orderCount; ++$i) {
            $placedAt = $this->createUtcDateTime('-'.$i.' hours');
            $orders[] = new PulledOrderDto(
                externalOrderId: 'MOCK_ORDER_'.time().'_'.$i,
                externalOrderNo: 'MO'.date('YmdHis').str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                status: 'paid',
                paymentStatus: 'paid',
                receiver: new ReceiverDto(
                    name: 'Mock Receiver '.$i,
                    phone: '1380000000'.$i,
                    address: 'Mock Address '.$i,
                    province: 'Mock Province',
                    city: 'Mock City',
                    district: 'Mock District',
                    postalCode: '10000'.$i,
                ),
                totalAmount: (string) (100 * $i),
                productAmount: (string) (100 * $i),
                shippingAmount: '0.00',
                discountAmount: '0.00',
                currency: $context->getCurrency(),
                placedAt: $placedAt,
                paidAt: $placedAt,
                items: [
                    new PulledOrderItemDto(
                        externalProductId: 'MOCK_PRODUCT_'.$i,
                        externalSkuId: 'MOCK_SKU_'.$i,
                        productName: 'Mock Product '.$i,
                        productImage: null,
                        quantity: $i,
                        unitPrice: '100.00',
                        totalPrice: (string) (100 * $i),
                        skuCode: 'MOCK_SKU_CODE_'.$i,
                        sizeValue: '42',
                    ),
                ],
                rawData: [
                    'mock' => true,
                    'generated_at' => $this->createUtcDateTime()->format(\DateTimeInterface::ATOM),
                ],
            );
        }

        return $orders;
    }
}
