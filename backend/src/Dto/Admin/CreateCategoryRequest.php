<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class CreateCategoryRequest
{
    #[Assert\NotBlank(message: 'name is required')]
    #[Assert\Length(max: 100, maxMessage: 'name must be at most 100 characters')]
    public string $name = '';

    #[Assert\Length(max: 100, maxMessage: 'slug must be at most 100 characters')]
    #[Assert\Regex(pattern: '/^[a-z0-9-]*$/', message: 'slug can only contain lowercase letters, numbers, and hyphens')]
    public ?string $slug = null;

    #[Assert\Uuid(message: 'parentId must be a valid UUID')]
    public ?string $parentId = null;

    #[Assert\Length(max: 500, maxMessage: 'description must be at most 500 characters')]
    public ?string $description = null;

    #[Assert\PositiveOrZero(message: 'sortOrder must be a non-negative integer')]
    public ?int $sortOrder = null;

    public ?bool $isActive = null;
}
