<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Exception;

/**
 * Base exception for channel gateway errors.
 */
class ChannelGatewayException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly ?int $httpStatus = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
