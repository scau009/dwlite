<?php

namespace App\Dto\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

class AssignMerchantRuleRequest
{
    #[Assert\NotBlank(message: 'Merchant sales channel ID is required')]
    #[Assert\Length(exactly: 26, exactMessage: 'Invalid merchant sales channel ID')]
    public string $merchantSalesChannelId;

    #[Assert\PositiveOrZero(message: 'Priority override must be a non-negative number')]
    public ?int $priorityOverride = null;

    public ?array $configOverride = null;

    public bool $isActive = true;
}
