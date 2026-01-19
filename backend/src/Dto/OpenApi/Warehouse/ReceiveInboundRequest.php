<?php

namespace App\Dto\OpenApi\Warehouse;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Individual item in the receive request.
 */
class ReceiveInboundItemDto
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $sku;

    #[Assert\NotNull]
    #[Assert\Type('integer')]
    #[Assert\Range(min: 0)]
    public int $receivedQuantity;

    #[Assert\Length(max: 200)]
    public ?string $shelvingLocation = null;

    #[Assert\Length(max: 500)]
    public ?string $notes = null;
}

/**
 * Request to complete receiving of inbound order.
 */
class ReceiveInboundRequest
{
    /**
     * @var ReceiveInboundItemDto[]
     */
    #[Assert\NotBlank]
    #[Assert\Valid]
    #[Assert\Count(min: 1)]
    public array $items;

    #[Assert\NotBlank]
    #[Assert\DateTime]
    public string $receivedAt;

    #[Assert\Length(max: 500)]
    public ?string $notes = null;
}
