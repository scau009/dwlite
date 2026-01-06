<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Dto\Response;

/**
 * DTO representing an order pulled from external channel.
 */
readonly class PulledOrderDto
{
    /**
     * @param PulledOrderItemDto[] $items
     * @param array<string, mixed>|null $rawData Original data for reference
     */
    public function __construct(
        public string $externalOrderId,
        public ?string $externalOrderNo,
        public string $status,
        public string $paymentStatus,
        public ReceiverDto $receiver,
        public string $totalAmount,
        public string $productAmount,
        public string $shippingAmount,
        public string $discountAmount,
        public string $currency,
        public \DateTimeImmutable $placedAt,
        public ?\DateTimeImmutable $paidAt,
        public array $items,
        public ?string $buyerRemark = null,
        public ?array $rawData = null,
    ) {
    }
}

/**
 * DTO for order line item pulled from channel.
 */
readonly class PulledOrderItemDto
{
    /**
     * @param array<string, mixed>|null $attributes Additional attributes
     */
    public function __construct(
        public string $externalProductId,
        public ?string $externalSkuId,
        public string $productName,
        public ?string $productImage,
        public int $quantity,
        public string $unitPrice,
        public string $totalPrice,
        public ?string $skuCode = null,
        public ?string $sizeValue = null,
        public ?array $attributes = null,
    ) {
    }
}

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
