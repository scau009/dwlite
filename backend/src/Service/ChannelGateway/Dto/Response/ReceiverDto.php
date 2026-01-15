<?php

namespace App\Service\ChannelGateway\Dto\Response;

/**
 * DTO for order receiver information.
 */
readonly class ReceiverDto
{
    public function __construct(
        public string $name,
        public string $phone,
        public string $address,
        public ?string $province = null,
        public ?string $city = null,
        public ?string $district = null,
        public ?string $postalCode = null,
    ) {
    }

    /**
     * Get full formatted address.
     */
    public function getFullAddress(): string
    {
        $parts = array_filter([
            $this->province,
            $this->city,
            $this->district,
            $this->address,
        ]);

        return implode(' ', $parts);
    }
}
