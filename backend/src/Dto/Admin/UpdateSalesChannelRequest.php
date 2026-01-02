<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateSalesChannelRequest
{
    #[Assert\Length(max: 100, maxMessage: 'validation.name_max_length')]
    public ?string $name = null;

    #[Assert\Url(message: 'validation.url_invalid')]
    public ?string $logoUrl = null;

    #[Assert\Length(max: 500, maxMessage: 'validation.description_max_length')]
    public ?string $description = null;

    public ?array $config = null;

    public ?array $configSchema = null;

    #[Assert\Choice(choices: ['import', 'export'], message: 'validation.business_type_invalid')]
    public ?string $businessType = null;

    #[Assert\PositiveOrZero(message: 'validation.sort_order_positive')]
    public ?int $sortOrder = null;
}
