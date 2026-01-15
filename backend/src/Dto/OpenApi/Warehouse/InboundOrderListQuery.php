<?php

namespace App\Dto\OpenApi\Warehouse;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Query parameters for listing inbound orders.
 */
class InboundOrderListQuery
{
    #[Assert\Type('integer')]
    #[Assert\Range(min: 1)]
    public int $page = 1;

    #[Assert\Type('integer')]
    #[Assert\Range(min: 1, max: 100)]
    public int $pageSize = 20;

    #[Assert\Choice(choices: ['pending', 'in_transit', 'arrived', 'receiving', 'completed', 'exception'])]
    public ?string $status = null;

    #[Assert\DateTime]
    public ?string $createdFrom = null;

    #[Assert\DateTime]
    public ?string $createdTo = null;

    #[Assert\Length(max: 50)]
    public ?string $orderNo = null;

    #[Assert\Length(max: 50)]
    public ?string $merchantCode = null;
}
