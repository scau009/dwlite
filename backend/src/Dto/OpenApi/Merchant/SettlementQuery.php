<?php

namespace App\Dto\OpenApi\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Query parameters for listing settlements.
 */
class SettlementQuery
{
    #[Assert\Type('integer')]
    #[Assert\Range(min: 1)]
    public int $page = 1;

    #[Assert\Type('integer')]
    #[Assert\Range(min: 1, max: 100)]
    public int $pageSize = 20;

    #[Assert\Choice(choices: ['pending', 'settled', 'cancelled'])]
    public ?string $status = null;

    #[Assert\DateTime]
    public ?string $scheduledSettleAtFrom = null;

    #[Assert\DateTime]
    public ?string $scheduledSettleAtTo = null;

    #[Assert\Length(max: 50)]
    public ?string $settlementNo = null;
}
