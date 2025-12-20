<?php

namespace App\Dto\Inbound;

use Symfony\Component\Validator\Constraints as Assert;

class ShipInboundOrderRequest
{
    #[Assert\NotBlank(message: 'validation.carrier_code_required')]
    #[Assert\Length(max: 20, maxMessage: 'validation.carrier_code_max_length')]
    public string $carrierCode = '';

    #[Assert\Length(max: 50, maxMessage: 'validation.carrier_name_max_length')]
    public ?string $carrierName = null;

    #[Assert\NotBlank(message: 'validation.tracking_number_required')]
    #[Assert\Length(max: 50, maxMessage: 'validation.tracking_number_max_length')]
    public string $trackingNumber = '';

    #[Assert\NotBlank(message: 'validation.sender_name_required')]
    #[Assert\Length(max: 50, maxMessage: 'validation.sender_name_max_length')]
    public string $senderName = '';

    #[Assert\NotBlank(message: 'validation.sender_phone_required')]
    #[Assert\Length(max: 20, maxMessage: 'validation.sender_phone_max_length')]
    public string $senderPhone = '';

    #[Assert\NotBlank(message: 'validation.sender_address_required')]
    #[Assert\Length(max: 255, maxMessage: 'validation.sender_address_max_length')]
    public string $senderAddress = '';

    #[Assert\Length(max: 50, maxMessage: 'validation.sender_province_max_length')]
    public ?string $senderProvince = null;

    #[Assert\Length(max: 50, maxMessage: 'validation.sender_city_max_length')]
    public ?string $senderCity = null;

    #[Assert\Positive(message: 'validation.box_count_must_positive')]
    public int $boxCount = 1;

    #[Assert\Type('numeric')]
    #[Assert\PositiveOrZero(message: 'validation.total_weight_must_non_negative')]
    public ?string $totalWeight = null;

    #[Assert\Type('numeric')]
    #[Assert\PositiveOrZero(message: 'validation.total_volume_must_non_negative')]
    public ?string $totalVolume = null;

    #[Assert\Type('DateTimeInterface')]
    public ?\DateTimeInterface $estimatedArrivalDate = null;

    #[Assert\Type('string')]
    public ?string $notes = null;
}