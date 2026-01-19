<?php

namespace App\Dto\OpenApi\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Query parameters for listing merchant inventory.
 */
class InventoryQuery
{
    #[Assert\Type('integer')]
    #[Assert\Range(min: 1)]
    public int $page = 1;

    #[Assert\Type('integer')]
    #[Assert\Range(min: 1, max: 100)]
    public int $pageSize = 20;

    #[Assert\Length(max: 100)]
    public ?string $sku = null;

    #[Assert\Length(max: 50)]
    public ?string $warehouseCode = null;

    #[Assert\Type('boolean')]
    public ?bool $lowStock = null;

    #[Assert\Type('boolean')]
    public ?bool $outOfStock = null;
}
