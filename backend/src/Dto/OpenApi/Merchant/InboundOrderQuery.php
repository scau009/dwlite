<?php

namespace App\Dto\OpenApi\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Query parameters for listing inbound orders.
 */
class InboundOrderQuery
{
    #[Assert\Type('integer')]
    #[Assert\Range(min: 1)]
    public int $page = 1;

    #[Assert\Type('integer')]
    #[Assert\Range(min: 1, max: 100)]
    public int $pageSize = 20;

    #[Assert\Choice(choices: ['draft', 'pending', 'shipped', 'arrived', 'receiving', 'completed', 'partial_completed', 'cancelled'])]
    public ?string $status = null;

    #[Assert\DateTime]
    public ?string $createdFrom = null;

    #[Assert\DateTime]
    public ?string $createdTo = null;

    #[Assert\Length(max: 50)]
    public ?string $orderNo = null;

    #[Assert\Length(max: 50)]
    public ?string $warehouseCode = null;
}
