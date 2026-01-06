<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Dto\Request;

/**
 * Request DTO for confirming order receipt to external channel.
 */
readonly class ConfirmOrderRequest
{
    public function __construct(
        public string $externalOrderId,
        public ?string $internalOrderId = null,
        public ?string $message = null,
    ) {
    }
}
