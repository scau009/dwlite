<?php

namespace App\Dto\OpenApi\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request to accept a fulfillment.
 */
class AcceptFulfillmentRequest
{
    #[Assert\NotBlank]
    #[Assert\DateTime]
    public string $acceptedAt;

    #[Assert\Length(max: 500)]
    public ?string $notes = null;
}
