<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateBrandRequest
{
    #[Assert\Length(max: 100, maxMessage: 'name must be at most 100 characters')]
    public ?string $name = null;

    #[Assert\Length(max: 100, maxMessage: 'slug must be at most 100 characters')]
    #[Assert\Regex(pattern: '/^[a-z0-9-]*$/', message: 'slug can only contain lowercase letters, numbers, and hyphens')]
    public ?string $slug = null;

    #[Assert\Url(message: 'logoUrl must be a valid URL')]
    public ?string $logoUrl = null;

    #[Assert\Length(max: 500, maxMessage: 'description must be at most 500 characters')]
    public ?string $description = null;

    #[Assert\PositiveOrZero(message: 'sortOrder must be a non-negative integer')]
    public ?int $sortOrder = null;

    public ?bool $isActive = null;
}
