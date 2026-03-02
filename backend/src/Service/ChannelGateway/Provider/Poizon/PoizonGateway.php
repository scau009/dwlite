<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Provider\Poizon;

use App\Entity\ChannelProduct;
use App\Service\ChannelGateway\AbstractChannelGateway;
use App\Service\ChannelGateway\ChannelGatewayContext;
use App\Service\ChannelGateway\Dto\Request\ConfirmOrderRequest;
use App\Service\ChannelGateway\Dto\Request\PullOrdersRequest;
use App\Service\ChannelGateway\Dto\Request\PushProductRequest;
use App\Service\ChannelGateway\Dto\Request\ShipOrderRequest;
use App\Service\ChannelGateway\Dto\Response\ChannelResponse;
use App\Service\ChannelGateway\Dto\Response\PushProductResponse;
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
    protected const SUPPORTED_OPERATIONS = [];

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
        throw new ChannelApiException('[POIZON] Operation not yet implemented', 'NOT_IMPLEMENTED', 501);
    }

    /**
     * @param ChannelProduct[] $channelProducts
     */
    public function updateStockPrice(
        ChannelGatewayContext $context,
        array $channelProducts
    ): UpdateStockPriceResponse {
        throw new ChannelApiException('[POIZON] Operation not yet implemented', 'NOT_IMPLEMENTED', 501);
    }

    public function delistProduct(
        ChannelGatewayContext $context,
        ChannelProduct $channelProduct
    ): ChannelResponse {
        throw new ChannelApiException('[POIZON] Operation not yet implemented', 'NOT_IMPLEMENTED', 501);
    }

    /**
     * @return never[]
     */
    public function pullOrders(
        ChannelGatewayContext $context,
        PullOrdersRequest $request
    ): array {
        throw new ChannelApiException('[POIZON] Operation not yet implemented', 'NOT_IMPLEMENTED', 501);
    }

    public function confirmOrder(
        ChannelGatewayContext $context,
        ConfirmOrderRequest $request
    ): ChannelResponse {
        throw new ChannelApiException('[POIZON] Operation not yet implemented', 'NOT_IMPLEMENTED', 501);
    }

    public function shipOrder(
        ChannelGatewayContext $context,
        ShipOrderRequest $request
    ): ShipOrderResponse {
        throw new ChannelApiException('[POIZON] Operation not yet implemented', 'NOT_IMPLEMENTED', 501);
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
}
