<?php

declare(strict_types=1);

namespace App\Service\OrderSync;

use App\Entity\Order;

/**
 * 渠道订单状态映射器.
 *
 * 将不同渠道的订单状态映射到平台统一状态。
 */
class ChannelStatusMapper
{
    /**
     * 各渠道订单状态映射表.
     *
     * @var array<string, array<string, string>>
     */
    private array $orderStatusMappings = [
        'TAOBAO' => [
            'WAIT_BUYER_PAY' => Order::STATUS_PENDING,
            'WAIT_SELLER_SEND_GOODS' => Order::STATUS_PENDING,
            'WAIT_BUYER_CONFIRM_GOODS' => Order::STATUS_SHIPPED,
            'TRADE_BUYER_SIGNED' => Order::STATUS_DELIVERED,
            'TRADE_FINISHED' => Order::STATUS_COMPLETED,
            'TRADE_CLOSED' => Order::STATUS_CANCELLED,
            'TRADE_CLOSED_BY_TAOBAO' => Order::STATUS_CANCELLED,
        ],
        'JD' => [
            'WAIT_PAY' => Order::STATUS_PENDING,
            'WAIT_CONFIRM' => Order::STATUS_PENDING,
            'WAIT_DELIVERY' => Order::STATUS_PENDING,
            'DELIVERING' => Order::STATUS_SHIPPED,
            'RECEIVED' => Order::STATUS_DELIVERED,
            'FINISHED' => Order::STATUS_COMPLETED,
            'LOCKED' => Order::STATUS_CANCELLED,
            'CANCELLED' => Order::STATUS_CANCELLED,
        ],
        'DOUYIN' => [
            'PAY_PENDING' => Order::STATUS_PENDING,
            'WAIT_SHIP' => Order::STATUS_PENDING,
            'SHIPPED' => Order::STATUS_SHIPPED,
            'CONFIRMED' => Order::STATUS_DELIVERED,
            'SUCCESS' => Order::STATUS_COMPLETED,
            'CLOSED' => Order::STATUS_CANCELLED,
            'REFUNDING' => Order::STATUS_CANCELLED,
        ],
    ];

    /**
     * 默认状态映射（小写匹配）.
     *
     * @var array<string, string>
     */
    private array $defaultOrderStatusMappings = [
        'pending' => Order::STATUS_PENDING,
        'paid' => Order::STATUS_PENDING,
        'processing' => Order::STATUS_PENDING,
        'shipped' => Order::STATUS_SHIPPED,
        'delivered' => Order::STATUS_DELIVERED,
        'completed' => Order::STATUS_COMPLETED,
        'cancelled' => Order::STATUS_CANCELLED,
        'refunded' => Order::STATUS_CANCELLED,
    ];

    /**
     * 各渠道支付状态映射表.
     *
     * @var array<string, array<string, string>>
     */
    private array $paymentStatusMappings = [
        'TAOBAO' => [
            'WAIT_BUYER_PAY' => Order::PAYMENT_PENDING,
            'BUYER_PAID' => Order::PAYMENT_PAID,
            'REFUND_SUCCESS' => Order::PAYMENT_REFUNDED,
            'PARTIAL_REFUND' => Order::PAYMENT_PARTIAL_REFUNDED,
        ],
        'JD' => [
            'WAIT_PAY' => Order::PAYMENT_PENDING,
            'PAID' => Order::PAYMENT_PAID,
            'REFUNDED' => Order::PAYMENT_REFUNDED,
        ],
        'DOUYIN' => [
            'UNPAID' => Order::PAYMENT_PENDING,
            'PAID' => Order::PAYMENT_PAID,
            'REFUNDED' => Order::PAYMENT_REFUNDED,
            'PARTIAL_REFUND' => Order::PAYMENT_PARTIAL_REFUNDED,
        ],
    ];

    /**
     * 默认支付状态映射（小写匹配）.
     *
     * @var array<string, string>
     */
    private array $defaultPaymentStatusMappings = [
        'pending' => Order::PAYMENT_PENDING,
        'unpaid' => Order::PAYMENT_PENDING,
        'paid' => Order::PAYMENT_PAID,
        'refunded' => Order::PAYMENT_REFUNDED,
        'partial_refunded' => Order::PAYMENT_PARTIAL_REFUNDED,
    ];

    /**
     * 映射渠道订单状态到平台状态.
     */
    public function mapOrderStatus(string $channelCode, string $channelStatus): string
    {
        $channelCode = strtoupper($channelCode);
        $channelStatusUpper = strtoupper($channelStatus);
        $channelStatusLower = strtolower($channelStatus);

        // 先尝试渠道特定映射
        if (isset($this->orderStatusMappings[$channelCode][$channelStatusUpper])) {
            return $this->orderStatusMappings[$channelCode][$channelStatusUpper];
        }

        // 再尝试默认映射
        if (isset($this->defaultOrderStatusMappings[$channelStatusLower])) {
            return $this->defaultOrderStatusMappings[$channelStatusLower];
        }

        // 默认返回待处理
        return Order::STATUS_PENDING;
    }

    /**
     * 映射渠道支付状态到平台状态.
     */
    public function mapPaymentStatus(string $channelCode, string $channelPaymentStatus): string
    {
        $channelCode = strtoupper($channelCode);
        $statusUpper = strtoupper($channelPaymentStatus);
        $statusLower = strtolower($channelPaymentStatus);

        // 先尝试渠道特定映射
        if (isset($this->paymentStatusMappings[$channelCode][$statusUpper])) {
            return $this->paymentStatusMappings[$channelCode][$statusUpper];
        }

        // 再尝试默认映射
        if (isset($this->defaultPaymentStatusMappings[$statusLower])) {
            return $this->defaultPaymentStatusMappings[$statusLower];
        }

        // 默认返回待支付
        return Order::PAYMENT_PENDING;
    }

    /**
     * 判断订单状态是否为已支付（需要确认）.
     */
    public function isPaidStatus(string $channelCode, string $channelPaymentStatus): bool
    {
        $platformStatus = $this->mapPaymentStatus($channelCode, $channelPaymentStatus);

        return $platformStatus === Order::PAYMENT_PAID;
    }

    /**
     * 判断订单状态是否为已发货.
     */
    public function isShippedStatus(string $channelCode, string $channelStatus): bool
    {
        $platformStatus = $this->mapOrderStatus($channelCode, $channelStatus);

        return in_array($platformStatus, [
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
            Order::STATUS_COMPLETED,
        ], true);
    }

    /**
     * 判断订单状态是否为已取消.
     */
    public function isCancelledStatus(string $channelCode, string $channelStatus): bool
    {
        $platformStatus = $this->mapOrderStatus($channelCode, $channelStatus);

        return $platformStatus === Order::STATUS_CANCELLED;
    }
}
