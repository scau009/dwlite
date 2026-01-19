<?php

namespace App\Dto\OpenApi\Warehouse;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Individual item adjustment.
 */
class AdjustmentItemDto
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $sku;

    #[Assert\NotNull]
    #[Assert\Type('integer')]
    public int $adjustmentQuantity;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['damaged', 'lost', 'found', 'correction', 'other'])]
    public string $reason;

    #[Assert\Length(max: 500)]
    public ?string $notes = null;
}

/**
 * Request to submit inventory adjustment.
 */
class AdjustmentRequest
{
    /**
     * @var AdjustmentItemDto[]
     */
    #[Assert\NotBlank]
    #[Assert\Valid]
    #[Assert\Count(min: 1)]
    public array $items;

    #[Assert\NotBlank]
    #[Assert\DateTime]
    public string $adjustedAt;

    #[Assert\Length(max: 100)]
    public ?string $adjustedBy = null;

    #[Assert\Length(max: 1000)]
    public ?string $notes = null;
}
