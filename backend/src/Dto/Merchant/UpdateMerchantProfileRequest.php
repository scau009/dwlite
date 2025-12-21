<?php

namespace App\Dto\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateMerchantProfileRequest
{
    #[Assert\NotBlank(message: 'validation.name_required')]
    #[Assert\Length(max: 100, maxMessage: 'validation.name_too_long')]
    public string $name;

    #[Assert\Length(max: 255, maxMessage: 'validation.description_too_long')]
    public ?string $description = null;

    #[Assert\NotBlank(message: 'validation.contact_name_required')]
    #[Assert\Length(max: 50, maxMessage: 'validation.contact_name_too_long')]
    public string $contactName;

    #[Assert\NotBlank(message: 'validation.contact_phone_required')]
    #[Assert\Length(max: 20, maxMessage: 'validation.contact_phone_too_long')]
    public string $contactPhone;

    #[Assert\Length(max: 50, maxMessage: 'validation.province_too_long')]
    public ?string $province = null;

    #[Assert\Length(max: 50, maxMessage: 'validation.city_too_long')]
    public ?string $city = null;

    #[Assert\Length(max: 50, maxMessage: 'validation.district_too_long')]
    public ?string $district = null;

    #[Assert\Length(max: 255, maxMessage: 'validation.address_too_long')]
    public ?string $address = null;
}
