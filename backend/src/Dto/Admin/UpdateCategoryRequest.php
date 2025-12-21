<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateCategoryRequest
{
    #[Assert\Length(max: 100, maxMessage: 'validation.name_max_length')]
    public ?string $name = null;

    #[Assert\Length(max: 100, maxMessage: 'validation.slug_max_length')]
    #[Assert\Regex(pattern: '/^[a-z0-9-]*$/', message: 'validation.slug_format')]
    public ?string $slug = null;

    #[Assert\Ulid(message: 'validation.ulid_invalid')]
    public ?string $parentId = null;

    #[Assert\Length(max: 500, maxMessage: 'validation.description_max_length')]
    public ?string $description = null;

    #[Assert\PositiveOrZero(message: 'validation.sort_order_positive')]
    public ?int $sortOrder = null;

    public ?bool $isActive = null;
}
