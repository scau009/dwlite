<?php

namespace App\Dto\Admin;

use App\Entity\PlatformRuleAssignment;
use Symfony\Component\Validator\Constraints as Assert;

class AssignPlatformRuleRequest
{
    #[Assert\NotBlank(message: 'Scope type is required')]
    #[Assert\Choice(
        choices: [PlatformRuleAssignment::SCOPE_MERCHANT, PlatformRuleAssignment::SCOPE_CHANNEL_PRODUCT],
        message: 'Invalid scope type'
    )]
    public string $scopeType;

    #[Assert\NotBlank(message: 'Scope ID is required')]
    #[Assert\Length(exactly: 26, exactMessage: 'Invalid scope ID')]
    public string $scopeId;

    #[Assert\PositiveOrZero(message: 'Priority override must be a non-negative number')]
    public ?int $priorityOverride = null;

    public ?array $configOverride = null;

    public bool $isActive = true;
}
