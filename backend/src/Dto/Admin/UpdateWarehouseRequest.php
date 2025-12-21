<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateWarehouseRequest
{
    #[Assert\Length(max: 50, maxMessage: 'validation.code_max_length')]
    public ?string $code = null;

    #[Assert\Length(max: 100, maxMessage: 'validation.name_max_length')]
    public ?string $name = null;

    #[Assert\Length(max: 100, maxMessage: 'validation.short_name_max_length')]
    public ?string $shortName = null;

    #[Assert\Choice(choices: ['self', 'third_party', 'bonded', 'overseas'], message: 'validation.type_invalid')]
    public ?string $type = null;

    #[Assert\Choice(choices: ['platform', 'merchant'], message: 'validation.category_invalid')]
    public ?string $category = null;

    public ?string $merchantId = null;

    public ?string $description = null;

    #[Assert\Length(exactly: 2, exactMessage: 'validation.country_code_length')]
    public ?string $countryCode = null;

    public ?string $timezone = null;

    public ?string $province = null;

    public ?string $city = null;

    public ?string $district = null;

    public ?string $address = null;

    public ?string $postalCode = null;

    public ?string $longitude = null;

    public ?string $latitude = null;

    #[Assert\Length(max: 50, maxMessage: 'validation.contact_name_max_length')]
    public ?string $contactName = null;

    #[Assert\Length(max: 20, maxMessage: 'validation.contact_phone_max_length')]
    public ?string $contactPhone = null;

    #[Assert\Email(message: 'validation.email_invalid')]
    public ?string $contactEmail = null;

    public ?string $internalNotes = null;

    #[Assert\Choice(choices: ['active', 'maintenance', 'disabled'], message: 'validation.status_invalid')]
    public ?string $status = null;

    #[Assert\PositiveOrZero(message: 'validation.sort_order_positive')]
    public ?int $sortOrder = null;
}
