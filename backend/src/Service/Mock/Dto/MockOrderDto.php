<?php

declare(strict_types=1);

namespace App\Service\Mock\Dto;

use App\Service\ChannelGateway\Dto\Response\PulledOrderDto;

/**
 * Mock order DTO for testing channel order flow.
 */
readonly class MockOrderDto
{
    public const STATUS_PENDING = 'pending';       // 待拉取
    public const STATUS_PULLED = 'pulled';         // 已拉取
    public const STATUS_CONFIRMED = 'confirmed';   // 已确认
    public const STATUS_SHIPPED = 'shipped';       // 已发货
    public const STATUS_DELIVERED = 'delivered';   // 已妥投
    public const STATUS_COMPLETED = 'completed';   // 已完成

    public const FULFILLMENT_CONSIGNMENT = 'consignment';        // 寄售（平台仓发货）
    public const FULFILLMENT_SELF = 'self_fulfillment';          // 自发货（商家仓发货）

    public function __construct(
        public string $mockOrderId,
        public string $channelProductId,
        public string $status,
        public string $fulfillmentType,
        public PulledOrderDto $orderData,
        public \DateTimeImmutable $createdAt,
        public ?string $internalOrderId = null,
    ) {
    }

    /**
     * Create array representation for Redis storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mockOrderId' => $this->mockOrderId,
            'channelProductId' => $this->channelProductId,
            'status' => $this->status,
            'fulfillmentType' => $this->fulfillmentType,
            'internalOrderId' => $this->internalOrderId,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'orderData' => $this->serializeOrderData($this->orderData),
        ];
    }

    /**
     * Restore from array representation.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            mockOrderId: $data['mockOrderId'],
            channelProductId: $data['channelProductId'],
            status: $data['status'],
            fulfillmentType: $data['fulfillmentType'],
            orderData: self::deserializeOrderData($data['orderData']),
            createdAt: new \DateTimeImmutable($data['createdAt'], new \DateTimeZone('UTC')),
            internalOrderId: $data['internalOrderId'] ?? null,
        );
    }

    /**
     * Create a new instance with updated status.
     */
    public function withStatus(string $status): self
    {
        return new self(
            mockOrderId: $this->mockOrderId,
            channelProductId: $this->channelProductId,
            status: $status,
            fulfillmentType: $this->fulfillmentType,
            orderData: $this->orderData,
            createdAt: $this->createdAt,
            internalOrderId: $this->internalOrderId,
        );
    }

    /**
     * Create a new instance with internal order ID.
     */
    public function withInternalOrderId(string $orderId): self
    {
        return new self(
            mockOrderId: $this->mockOrderId,
            channelProductId: $this->channelProductId,
            status: $this->status,
            fulfillmentType: $this->fulfillmentType,
            orderData: $this->orderData,
            createdAt: $this->createdAt,
            internalOrderId: $orderId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrderData(PulledOrderDto $order): array
    {
        $items = [];
        foreach ($order->items as $item) {
            $items[] = [
                'externalProductId' => $item->externalProductId,
                'externalSkuId' => $item->externalSkuId,
                'productName' => $item->productName,
                'productImage' => $item->productImage,
                'quantity' => $item->quantity,
                'unitPrice' => $item->unitPrice,
                'totalPrice' => $item->totalPrice,
                'skuCode' => $item->skuCode,
                'sizeValue' => $item->sizeValue,
                'attributes' => $item->attributes,
            ];
        }

        return [
            'externalOrderId' => $order->externalOrderId,
            'externalOrderNo' => $order->externalOrderNo,
            'status' => $order->status,
            'paymentStatus' => $order->paymentStatus,
            'receiver' => [
                'name' => $order->receiver->name,
                'phone' => $order->receiver->phone,
                'address' => $order->receiver->address,
                'province' => $order->receiver->province,
                'city' => $order->receiver->city,
                'district' => $order->receiver->district,
                'postalCode' => $order->receiver->postalCode,
            ],
            'totalAmount' => $order->totalAmount,
            'productAmount' => $order->productAmount,
            'shippingAmount' => $order->shippingAmount,
            'discountAmount' => $order->discountAmount,
            'currency' => $order->currency,
            'placedAt' => $order->placedAt->format(\DateTimeInterface::ATOM),
            'paidAt' => $order->paidAt?->format(\DateTimeInterface::ATOM),
            'items' => $items,
            'buyerRemark' => $order->buyerRemark,
            'rawData' => $order->rawData,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function deserializeOrderData(array $data): PulledOrderDto
    {
        $items = [];
        foreach ($data['items'] as $itemData) {
            $items[] = new \App\Service\ChannelGateway\Dto\Response\PulledOrderItemDto(
                externalProductId: $itemData['externalProductId'],
                externalSkuId: $itemData['externalSkuId'],
                productName: $itemData['productName'],
                productImage: $itemData['productImage'],
                quantity: $itemData['quantity'],
                unitPrice: $itemData['unitPrice'],
                totalPrice: $itemData['totalPrice'],
                skuCode: $itemData['skuCode'] ?? null,
                sizeValue: $itemData['sizeValue'] ?? null,
                attributes: $itemData['attributes'] ?? null,
            );
        }

        return new PulledOrderDto(
            externalOrderId: $data['externalOrderId'],
            externalOrderNo: $data['externalOrderNo'],
            status: $data['status'],
            paymentStatus: $data['paymentStatus'],
            receiver: new \App\Service\ChannelGateway\Dto\Response\ReceiverDto(
                name: $data['receiver']['name'],
                phone: $data['receiver']['phone'],
                address: $data['receiver']['address'],
                province: $data['receiver']['province'] ?? null,
                city: $data['receiver']['city'] ?? null,
                district: $data['receiver']['district'] ?? null,
                postalCode: $data['receiver']['postalCode'] ?? null,
            ),
            totalAmount: $data['totalAmount'],
            productAmount: $data['productAmount'],
            shippingAmount: $data['shippingAmount'],
            discountAmount: $data['discountAmount'],
            currency: $data['currency'],
            placedAt: new \DateTimeImmutable($data['placedAt'], new \DateTimeZone('UTC')),
            paidAt: $data['paidAt'] !== null ? new \DateTimeImmutable($data['paidAt'], new \DateTimeZone('UTC')) : null,
            items: $items,
            buyerRemark: $data['buyerRemark'] ?? null,
            rawData: $data['rawData'] ?? null,
        );
    }
}
