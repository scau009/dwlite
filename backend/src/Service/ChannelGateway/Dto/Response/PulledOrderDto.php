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
