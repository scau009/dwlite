<?php

namespace App\Dto\Inbound\Query;

use Symfony\Component\Validator\Constraints as Assert;

class InboundOrderListQuery
{
    #[Assert\Choice(
        choices: ['draft', 'pending', 'shipped', 'arrived', 'receiving', 'completed', 'partial_completed', 'cancelled'],
        message: 'validation.invalid_status'
    )]
    public ?string $status = null;

    #[Assert\Type('string')]
    public ?string $warehouseId = null;

    #[Assert\Type('string')]
    public ?string $orderNo = null;

    #[Assert\Positive(message: 'validation.page_must_positive')]
    public int $page = 1;

    #[Assert\Positive(message: 'validation.limit_must_positive')]
    #[Assert\LessThanOrEqual(100, message: 'validation.limit_max_100')]
    public int $limit = 20;
}