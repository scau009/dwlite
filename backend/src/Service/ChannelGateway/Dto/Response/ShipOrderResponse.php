<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Dto\Response;

/**
 * Response DTO for ship order operation.
 */
readonly class ShipOrderResponse extends ChannelResponse
{
    public function __construct(
        bool $success,
        string $channelCode,
        ?string $externalId = null,
        public bool $trackingAccepted = false,
        ?string $message = null,
        ?array $data = null,
        ?\DateTimeImmutable $timestamp = null,
    ) {
        parent::__construct($success, $channelCode, $externalId, $message, $data, $timestamp);
    }
}
