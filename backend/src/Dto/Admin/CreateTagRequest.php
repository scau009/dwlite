<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class CreateTagRequest
{
    #[Assert\NotBlank(message: 'validation.name_required')]
    #[Assert\Length(max: 50, maxMessage: 'validation.name_max_length')]
    public string $name = '';

    #[Assert\Length(max: 60, maxMessage: 'validation.slug_max_length')]
    #[Assert\Regex(pattern: '/^[a-z0-9-]*$/', message: 'validation.slug_format')]
    public ?string $slug = null;

    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'validation.color_format')]
    public ?string $color = null;

    #[Assert\PositiveOrZero(message: 'validation.sort_order_positive')]
    public ?int $sortOrder = null;

    public ?bool $isActive = null;
}
