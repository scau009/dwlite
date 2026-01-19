<?php

namespace App\Dto\OpenApi\Warehouse;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Individual item in stocktake.
 */
class StocktakeItemDto
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $sku;

    #[Assert\NotNull]
    #[Assert\Type('integer')]
    #[Assert\Range(min: 0)]
    public int $countedQuantity;

    #[Assert\Length(max: 200)]
    public ?string $shelvingLocation = null;

    #[Assert\Length(max: 500)]
    public ?string $notes = null;
}

/**
 * Request to submit stocktake results.
 */
class StocktakeRequest
{
    /**
     * @var StocktakeItemDto[]
     */
    #[Assert\NotBlank]
    #[Assert\Valid]
    #[Assert\Count(min: 1)]
    public array $items;

    #[Assert\NotBlank]
    #[Assert\DateTime]
    public string $countedAt;

    #[Assert\Length(max: 100)]
    public ?string $counterName = null;

    #[Assert\Length(max: 500)]
    public ?string $notes = null;
}
