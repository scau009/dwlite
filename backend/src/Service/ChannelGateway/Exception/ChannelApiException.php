<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Exception;

/**
 * Exception for general API errors from external channels.
 */
class ChannelApiException extends ChannelGatewayException
{
    public function __construct(
        string $message,
        ?string $errorCode = null,
        ?int $httpStatus = null,
        public readonly ?array $responseData = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $errorCode, $httpStatus, $previous);
    }
}
