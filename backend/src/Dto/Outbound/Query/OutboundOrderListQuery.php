<?php

namespace App\Dto\Outbound\Query;

use Symfony\Component\Validator\Constraints as Assert;

class OutboundOrderListQuery
{
    #[Assert\Choice(
        choices: ['pending', 'picking', 'packing', 'ready', 'shipped', 'cancelled'],
        message: 'validation.invalid_status'
    )]
    public ?string $status = null;

    #[Assert\Choice(
        choices: ['sales', 'return_to_merchant', 'transfer', 'scrap'],
        message: 'validation.invalid_outbound_type'
    )]
    public ?string $outboundType = null;

    #[Assert\Type('string')]
    public ?string $warehouseId = null;

    #[Assert\Type('string')]
    public ?string $outboundNo = null;

    #[Assert\Type('string')]
    public ?string $trackingNumber = null;

    #[Assert\Positive(message: 'validation.page_must_positive')]
    public int $page = 1;

    #[Assert\Positive(message: 'validation.limit_must_positive')]
    #[Assert\LessThanOrEqual(100, message: 'validation.limit_max_100')]
    public int $limit = 20;
}
