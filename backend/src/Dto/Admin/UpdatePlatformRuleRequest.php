<?php

namespace App\Dto\Admin;

use App\Entity\PlatformRule;
use Symfony\Component\Validator\Constraints as Assert;

class UpdatePlatformRuleRequest
{
    #[Assert\Length(max: 200, maxMessage: 'Rule name cannot exceed {{ limit }} characters')]
    public ?string $name = null;

    #[Assert\Length(max: 1000, maxMessage: 'Description cannot exceed {{ limit }} characters')]
    public ?string $description = null;

    #[Assert\Choice(
        choices: [
            PlatformRule::CATEGORY_MARKUP,
            PlatformRule::CATEGORY_DISCOUNT,
            PlatformRule::CATEGORY_PRIORITY,
            PlatformRule::CATEGORY_FEE_RATE,
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
