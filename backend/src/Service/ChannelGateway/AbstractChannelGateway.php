<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway;

use App\Service\ChannelGateway\Dto\Response\ChannelResponse;
use App\Service\ChannelGateway\Exception\ChannelApiException;
use App\Service\ChannelGateway\Exception\ChannelAuthException;
use App\Service\ChannelGateway\Exception\ChannelRateLimitException;
use Psr\Log\LoggerInterface;

/**
 * Abstract base class for channel gateways.
 *
 * Provides common functionality like logging, error handling,
 * and response wrapping.
 */
abstract class AbstractChannelGateway implements ChannelGatewayInterface
{
    /** @var string[] */
    protected const SUPPORTED_OPERATIONS = [
        ChannelGatewayInterface::OPERATION_PUSH_PRODUCT,
        ChannelGatewayInterface::OPERATION_UPDATE_STOCK_PRICE,
        ChannelGatewayInterface::OPERATION_PULL_ORDERS,
        ChannelGatewayInterface::OPERATION_CONFIRM_ORDER,
        ChannelGatewayInterface::OPERATION_SHIP_ORDER,
    ];

    public function __construct(
        protected LoggerInterface $logger,
    ) {
    }

    public function supports(string $operation): bool
    {
        return in_array($operation, static::SUPPORTED_OPERATIONS, true);
    }

    /**
     * Log operation start with context.
     *
     * @param array<string, mixed> $context
     */
    protected function logOperationStart(string $operation, array $context = []): void
    {
        $this->logger->info(sprintf('[%s] Starting %s', $this->getChannelCode(), $operation), $context);
    }

    /**
     * Log operation success.
     *
     * @param array<string, mixed> $context
     */
    protected function logOperationSuccess(string $operation, array $context = []): void
    {
        $this->logger->info(sprintf('[%s] %s completed successfully', $this->getChannelCode(), $operation), $context);
    }

    /**
     * Log operation failure.
     *
     * @param array<string, mixed> $context
     */
    protected function logOperationFailure(string $operation, \Throwable $e, array $context = []): void
    {
        $this->logger->error(sprintf('[%s] %s failed: %s', $this->getChannelCode(), $operation, $e->getMessage()), array_merge($context, [
            'exception' => $e::class,
            'trace' => $e->getTraceAsString(),
        ]));
    }

    /**
     * Wrap API response with standard format.
     *
     * @param array<string, mixed>|null $data
     */
    protected function wrapResponse(bool $success, ?string $externalId = null, ?string $message = null, ?array $data = null): ChannelResponse
    {
        return new ChannelResponse(
            success: $success,
            channelCode: $this->getChannelCode(),
            externalId: $externalId,
            message: $message,
            data: $data,
            timestamp: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }

    /**
     * Handle common API errors and convert to exceptions.
     *
     * @throws ChannelAuthException
     * @throws ChannelRateLimitException
     * @throws ChannelApiException
     */
    protected function handleApiError(int $statusCode, string $errorCode, string $errorMessage): never
    {
        if ($statusCode === 401 || $statusCode === 403) {
            throw new ChannelAuthException(sprintf('[%s] Authentication failed: %s', $this->getChannelCode(), $errorMessage), $errorCode);
        }

        if ($statusCode === 429) {
            throw new ChannelRateLimitException(sprintf('[%s] Rate limit exceeded: %s', $this->getChannelCode(), $errorMessage));
        }

        throw new ChannelApiException(sprintf('[%s] API error: %s', $this->getChannelCode(), $errorMessage), $errorCode, $statusCode);
    }

    /**
     * Create a UTC DateTimeImmutable.
     */
    protected function createUtcDateTime(string $datetime = 'now'): \DateTimeImmutable
    {
        return new \DateTimeImmutable($datetime, new \DateTimeZone('UTC'));
    }
}
