<?php

namespace App\Dto\OpenApi\Warehouse;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request to complete packing of outbound order.
 */
class CompletePackingRequest
{
    #[Assert\NotBlank]
    #[Assert\DateTime]
    public string $completedAt;

    #[Assert\Length(max: 100)]
    public ?string $packerName = null;

    #[Assert\Type('numeric')]
    #[Assert\Range(min: 0)]
    public ?float $packageWeight = null;

    #[Assert\Length(max: 50)]
    public ?string $packageDimensions = null;

    #[Assert\Length(max: 500)]
    public ?string $notes = null;
}
