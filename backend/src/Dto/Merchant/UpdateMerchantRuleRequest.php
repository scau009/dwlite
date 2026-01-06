<?php

namespace App\Dto\Merchant;

use App\Entity\MerchantRule;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateMerchantRuleRequest
{
    #[Assert\Length(max: 200, maxMessage: 'Rule name cannot exceed {{ limit }} characters')]
    public ?string $name = null;

    #[Assert\Length(max: 1000, maxMessage: 'Description cannot exceed {{ limit }} characters')]
    public ?string $description = null;

    #[Assert\Choice(
        choices: [
            MerchantRule::CATEGORY_MARKUP,
            MerchantRule::CATEGORY_DISCOUNT,
            MerchantRule::CATEGORY_RATIO,
            MerchantRule::CATEGORY_LIMIT,
        ],
        message: 'Invalid rule category'
    )]
    public ?string $category = null;

    public ?string $expression = null;

    public ?string $conditionExpression = null;

    #[Assert\PositiveOrZero(message: 'Priority must be a non-negative number')]
    public ?int $priority = null;

    public ?array $config = null;

    public ?bool $isActive = null;
}
