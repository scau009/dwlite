<?php

namespace App\Dto\OpenApi\Warehouse;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request to confirm inbound order arrival.
 */
class ConfirmArrivalRequest
{
    #[Assert\NotBlank]
    #[Assert\DateTime]
    public string $arrivedAt;

    #[Assert\Length(max: 500)]
    public ?string $notes = null;
}
