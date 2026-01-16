<?php

namespace App\Dto\OpenApi\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request to reject a fulfillment.
 */
class RejectFulfillmentRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    public string $reason;

    #[Assert\NotBlank]
    #[Assert\DateTime]
    public string $rejectedAt;
}
