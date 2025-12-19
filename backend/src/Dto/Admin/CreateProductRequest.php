<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class CreateProductRequest
{
    #[Assert\NotBlank(message: 'validation.name_required')]
    #[Assert\Length(max: 200, maxMessage: 'validation.name_max_length')]
    public string $name = '';

    #[Assert\Length(max: 220, maxMessage: 'validation.slug_max_length')]
    #[Assert\Regex(pattern: '/^[a-z0-9-]*$/', message: 'validation.slug_format')]
    public ?string $slug = null;

    #[Assert\NotBlank(message: 'validation.style_number_required')]
    #[Assert\Length(max: 50, maxMessage: 'validation.style_number_max_length')]
    public string $styleNumber = '';

    #[Assert\NotBlank(message: 'validation.season_required')]
    #[Assert\Length(max: 20, maxMessage: 'validation.season_max_length')]
    public string $season = '';

    #[Assert\Length(max: 50, maxMessage: 'validation.color_max_length')]
    public ?string $color = null;

    public ?string $description = null;

    public ?string $brandId = null;

    public ?string $categoryId = null;

    #[Assert\Choice(choices: ['draft', 'active', 'inactive', 'discontinued'], message: 'validation.invalid_status')]
    public string $status = 'draft';

    /** @var string[]|null */
    public ?array $tagIds = null;
}
