<?php

namespace App\Dto\Merchant;

use App\Entity\MerchantRule;
use Symfony\Component\Validator\Constraints as Assert;

class CreateMerchantRuleRequest
{
    #[Assert\NotBlank(message: 'Rule code is required')]
    #[Assert\Length(max: 100, maxMessage: 'Rule code cannot exceed {{ limit }} characters')]
    #[Assert\Regex(
        pattern: '/^[a-z][a-z0-9_]*$/',
        message: 'Rule code must start with a letter and contain only lowercase letters, numbers, and underscores'
    )]
    public string $code;

    #[Assert\NotBlank(message: 'Rule name is required')]
    #[Assert\Length(max: 200, maxMessage: 'Rule name cannot exceed {{ limit }} characters')]
    public string $name;

    #[Assert\Length(max: 1000, maxMessage: 'Description cannot exceed {{ limit }} characters')]
    public ?string $description = null;

    #[Assert\NotBlank(message: 'Rule type is required')]
    #[Assert\Choice(
        choices: [MerchantRule::TYPE_PRICING, MerchantRule::TYPE_STOCK_ALLOCATION],
        message: 'Invalid rule type'
    )]
    public string $type;

    #[Assert\NotBlank(message: 'Rule category is required')]
    #[Assert\Choice(
        choices: [
            MerchantRule::CATEGORY_MARKUP,
            MerchantRule::CATEGORY_DISCOUNT,
            MerchantRule::CATEGORY_RATIO,
            MerchantRule::CATEGORY_LIMIT,
        ],
        message: 'Invalid rule category'
    )]
    public string $category;

    #[Assert\NotBlank(message: 'Expression is required')]
    public string $expression;

    public ?string $conditionExpression = null;

    #[Assert\PositiveOrZero(message: 'Priority must be a non-negative number')]
    public int $priority = 0;

    public ?array $config = null;

    public bool $isActive = true;
}
