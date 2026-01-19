<?php

namespace App\Dto\OpenApi\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Query parameters for listing listings.
 */
class ListingQuery
{
    #[Assert\Type('integer')]
    #[Assert\Range(min: 1)]
    public int $page = 1;

    #[Assert\Type('integer')]
    #[Assert\Range(min: 1, max: 100)]
    public int $pageSize = 20;

    #[Assert\Choice(choices: ['draft', 'active', 'paused', 'sold_out'])]
    public ?string $status = null;

    #[Assert\Length(max: 50)]
    public ?string $channelCode = null;

    #[Assert\Length(max: 100)]
    public ?string $sku = null;
}
