<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway;

use App\Service\ChannelGateway\Dto\Request\ConfirmOrderRequest;
use App\Service\ChannelGateway\Dto\Request\PullOrdersRequest;
use App\Service\ChannelGateway\Dto\Request\PushProductRequest;
use App\Service\ChannelGateway\Dto\Request\ShipOrderRequest;
use App\Service\ChannelGateway\Dto\Request\UpdateStockPriceRequest;
use App\Service\ChannelGateway\Dto\Response\ChannelResponse;
use App\Service\ChannelGateway\Dto\Response\PulledOrderDto;
use App\Service\ChannelGateway\Dto\Response\PushProductResponse;
use App\Service\ChannelGateway\Dto\Response\ShipOrderResponse;
use App\Service\ChannelGateway\Dto\Response\UpdateStockPriceResponse;

/**
 * Interface for external sales channel gateways.
 *
 * Each channel (Taobao, JD, Douyin, etc.) implements this interface
 * to provide a unified API for channel operations.
 */
interface ChannelGatewayInterface
{
    public const OPERATION_PUSH_PRODUCT = 'pushProduct';
    public const OPERATION_UPDATE_STOCK_PRICE = 'updateStockPrice';
    public const OPERATION_PULL_ORDERS = 'pullOrders';
    public const OPERATION_CONFIRM_ORDER = 'confirmOrder';
    public const OPERATION_SHIP_ORDER = 'shipOrder';

    /**
     * Get the unique channel code.
     * Must match SalesChannel.code (e.g., 'TAOBAO', 'JD', 'DOUYIN').
     */
    public function getChannelCode(): string;

    /**
     * Get human-readable channel name.
     */
    public function getChannelName(): string;

    /**
     * Check if the gateway supports a specific operation.
     */
    public function supports(string $operation): bool;

    /**
     * Push product listing to the external channel.
     * Creates or updates product on the external platform.
     */
    public function pushProduct(
        ChannelGatewayContext $context,
        PushProductRequest $request
    ): PushProductResponse;

    /**
     * Update stock and price on the external channel.
     * Supports partial updates (stock only, price only, or both).
     */
    public function updateStockPrice(
        ChannelGatewayContext $context,
        UpdateStockPriceRequest $request
    ): UpdateStockPriceResponse;

    /**
     * Pull orders from the external channel.
     * Returns orders within the specified time range.
     *
     * @return PulledOrderDto[]
     */
    public function pullOrders(
        ChannelGatewayContext $context,
        PullOrdersRequest $request
    ): array;

    /**
     * Confirm order receipt to the external channel.
     * Acknowledges that the order has been received and is being processed.
     */
    public function confirmOrder(
        ChannelGatewayContext $context,
        ConfirmOrderRequest $request
    ): ChannelResponse;

    /**
     * Push shipping/fulfillment info to the external channel.
     * Sends tracking number and carrier information.
     */
    public function shipOrder(
        ChannelGatewayContext $context,
        ShipOrderRequest $request
    ): ShipOrderResponse;

    /**
     * Test connection with provided credentials.
     * Used for validating merchant configuration.
     */
    public function testConnection(ChannelGatewayContext $context): bool;
}
