<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Exception;

/**
 * Exception for rate limit exceeded when calling external channel APIs.
 */
class ChannelRateLimitException extends ChannelGatewayException
{
    public function __construct(
        string $message,
        public readonly ?int $retryAfter = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 'RATE_LIMIT', 429, $previous);
    }
}
