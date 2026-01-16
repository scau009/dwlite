<?php

namespace App\Dto\OpenApi\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Query parameters for listing fulfillments.
 */
class FulfillmentQuery
{
    #[Assert\Type('integer')]
    #[Assert\Range(min: 1)]
    public int $page = 1;

    #[Assert\Type('integer')]
    #[Assert\Range(min: 1, max: 100)]
    public int $pageSize = 20;

    #[Assert\Choice(choices: ['allocated', 'accepted', 'rejected', 'picking', 'packing', 'shipped', 'expired'])]
    public ?string $status = null;

    #[Assert\DateTime]
    public ?string $createdFrom = null;

    #[Assert\DateTime]
    public ?string $createdTo = null;

    #[Assert\Length(max: 50)]
    public ?string $fulfillmentNo = null;
}
