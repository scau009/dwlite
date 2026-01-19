<?php

namespace App\Dto\OpenApi\Warehouse;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Individual item picked.
 */
class PickedItemDto
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $sku;

    #[Assert\NotNull]
    #[Assert\Type('integer')]
    #[Assert\Range(min: 0)]
    public int $pickedQuantity;

    #[Assert\Length(max: 200)]
    public ?string $shelvingLocation = null;
}

/**
 * Request to complete picking of outbound order.
 */
class CompletePickingRequest
{
    /**
     * @var PickedItemDto[]
     */
    #[Assert\NotBlank]
    #[Assert\Valid]
    #[Assert\Count(min: 1)]
    public array $items;

    #[Assert\NotBlank]
    #[Assert\DateTime]
    public string $completedAt;

    #[Assert\Length(max: 500)]
    public ?string $notes = null;
}
