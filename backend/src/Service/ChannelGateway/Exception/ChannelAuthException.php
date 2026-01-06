<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Exception;

/**
 * Exception for authentication failures with external channels.
 */
class ChannelAuthException extends ChannelGatewayException
{
    public function __construct(
        string $message,
        ?string $errorCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $errorCode, 401, $previous);
    }
}
