<?php

namespace App\Dto\OpenApi\Warehouse;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request to start picking an outbound order.
 */
class StartPickingRequest
{
    #[Assert\NotBlank]
    #[Assert\DateTime]
    public string $startedAt;

    #[Assert\Length(max: 100)]
    public ?string $pickerName = null;
}
