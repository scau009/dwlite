<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class CreateSalesChannelRequest
{
    #[Assert\NotBlank(message: 'validation.code_required')]
    #[Assert\Length(max: 50, maxMessage: 'validation.code_max_length')]
    #[Assert\Regex(pattern: '/^[a-z0-9_]+$/', message: 'validation.code_format')]
    public string $code = '';

    #[Assert\NotBlank(message: 'validation.name_required')]
    #[Assert\Length(max: 100, maxMessage: 'validation.name_max_length')]
    public string $name = '';

    #[Assert\Url(message: 'validation.url_invalid')]
    public ?string $logoUrl = null;

    #[Assert\Length(max: 500, maxMessage: 'validation.description_max_length')]
    public ?string $description = null;

    public ?array $config = null;

    public ?array $configSchema = null;

    #[Assert\Choice(choices: ['import', 'export'], message: 'validation.business_type_invalid')]
    public string $businessType = 'export';

    #[Assert\Choice(choices: ['active', 'maintenance', 'disabled'], message: 'validation.status_invalid')]
    public string $status = 'active';

    #[Assert\PositiveOrZero(message: 'validation.sort_order_positive')]
    public ?int $sortOrder = null;
}