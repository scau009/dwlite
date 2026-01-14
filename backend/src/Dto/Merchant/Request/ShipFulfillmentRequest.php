<?php

declare(strict_types=1);

namespace App\Dto\Merchant\Request;

use Symfony\Component\Validator\Constraints as Assert;

class ShipFulfillmentRequest
{
    #[Assert\NotBlank(message: 'validation.carrier_required')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'validation.carrier_too_short',
        maxMessage: 'validation.carrier_too_long'
    )]
    public string $carrier = '';

    #[Assert\NotBlank(message: 'validation.tracking_number_required')]
    #[Assert\Length(
        min: 5,
        max: 100,
        minMessage: 'validation.tracking_number_too_short',
        maxMessage: 'validation.tracking_number_too_long'
    )]
    public string $trackingNumber = '';

    #[Assert\Length(
        max: 500,
        maxMessage: 'validation.tracking_url_too_long'
    )]
    #[Assert\Url(message: 'validation.tracking_url_invalid')]
    public ?string $trackingUrl = null;
}
