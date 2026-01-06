<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Dto\Request;

/**
 * Request DTO for pushing shipping info to external channel.
 */
readonly class ShipOrderRequest
{
    public function __construct(
        public string $externalOrderId,
        public string $trackingNumber,
        public string $shippingCarrier,
        public ?string $shippingCarrierCode = null,
        public ?\DateTimeImmutable $shippedAt = null,
    ) {
    }
}
