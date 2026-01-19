<?php

namespace App\Dto\OpenApi\Warehouse;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request to mark outbound order as shipped.
 */
class ShipOutboundRequest
{
    #[Assert\NotBlank]
    #[Assert\DateTime]
    public string $shippedAt;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $shippingCarrier;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $trackingNumber;

    #[Assert\Url]
    public ?string $trackingUrl = null;

    #[Assert\Length(max: 500)]
    public ?string $notes = null;
}
