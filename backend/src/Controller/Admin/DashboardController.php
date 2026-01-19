<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Attribute\AdminOnly;
use App\Entity\Order;
use App\Entity\OrderException;
use App\Repository\ChannelProductRepository;
use App\Repository\FulfillmentRepository;
use App\Repository\InboundExceptionRepository;
use App\Repository\MerchantRepository;
use App\Repository\OrderExceptionRepository;
use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * 平台管理员 - 工作台统计.
 */
#[Route('/api/admin/dashboard')]
#[AdminOnly]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly FulfillmentRepository $fulfillmentRepository,
        private readonly OrderExceptionRepository $orderExceptionRepository,
        private readonly InboundExceptionRepository $inboundExceptionRepository,
        private readonly ChannelProductRepository $channelProductRepository,
        private readonly MerchantRepository $merchantRepository,
    ) {
    }

    /**
     * 获取工作台统计数据.
     */
    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        // 订单统计
        $todayOrders = $this->orderRepository->countToday();
        $yesterdayOrders = $this->orderRepository->countYesterday();
        $todayRevenue = $this->orderRepository->sumTodayRevenue();
        $yesterdayRevenue = $this->orderRepository->sumYesterdayRevenue();

        // 计算增长率
        $ordersGrowth = $this->calculateGrowth($todayOrders, $yesterdayOrders);
        $revenueGrowth = $this->calculateGrowth((float) $todayRevenue, (float) $yesterdayRevenue);

        // 订单状态分布
        $orderStatusCounts = $this->orderRepository->countByStatus();

        // 履约单状态分布
        $fulfillmentStatusCounts = $this->fulfillmentRepository->countByStatus();
        $fulfillmentTypeCounts = $this->fulfillmentRepository->countByFulfillmentType();

        // 异常统计
        $pendingOrderExceptions = $this->orderExceptionRepository->countAllPending();
        $pendingInboundExceptions = $this->inboundExceptionRepository->countAllPending();

        // 渠道商品统计
        $channelStats = $this->channelProductRepository->getSummaryStats();

        // 商户统计
        $merchantStats = $this->merchantRepository->getSummaryStats();

        // 分配失败的订单数
        $allocationFailed = $orderStatusCounts[Order::STATUS_ALLOCATION_FAILED] ?? 0;

        return $this->json([
            'data' => [
                'summary' => [
                    'todayOrders' => $todayOrders,
                    'todayOrdersGrowth' => $ordersGrowth,
                    'todayRevenue' => $todayRevenue,
                    'todayRevenueGrowth' => $revenueGrowth,
                    'pendingExceptions' => $pendingOrderExceptions,
                    'allocationFailed' => $allocationFailed,
                ],
                'orderStats' => [
                    'byStatus' => $orderStatusCounts,
                ],
                'fulfillmentStats' => [
                    'byStatus' => $fulfillmentStatusCounts,
                    'byType' => $fulfillmentTypeCounts,
                ],
                'exceptionStats' => [
                    'orderExceptions' => $pendingOrderExceptions,
                    'inboundExceptions' => $pendingInboundExceptions,
                ],
                'channelStats' => $channelStats,
                'merchantStats' => $merchantStats,
            ],
        ]);
    }

    /**
     * 获取近7天的趋势数据.
     */
    #[Route('/trend', methods: ['GET'])]
    public function trend(): JsonResponse
    {
        $timezone = new \DateTimeZone('Asia/Shanghai');
        $endDate = new \DateTimeImmutable('tomorrow', $timezone);
        $startDate = new \DateTimeImmutable('-6 days', $timezone);

        // 转换为 UTC 进行查询
        $startDateUtc = $startDate->setTimezone(new \DateTimeZone('UTC'));
        $endDateUtc = $endDate->setTimezone(new \DateTimeZone('UTC'));

        $orderCounts = $this->orderRepository->countByDateRange($startDateUtc, $endDateUtc);
        $fulfillmentCounts = $this->fulfillmentRepository->countByDateRange($startDateUtc, $endDateUtc);

        // 生成近7天的日期列表
        $trend = [];
        for ($i = 6; $i >= 0; --$i) {
            $date = (new \DateTimeImmutable("-{$i} days", $timezone))->format('Y-m-d');
            $trend[] = [
                'date' => $date,
                'orderCount' => $orderCounts[$date] ?? 0,
                'fulfillmentCount' => $fulfillmentCounts[$date] ?? 0,
            ];
        }

        return $this->json([
            'data' => $trend,
        ]);
    }

    /**
     * 获取最近的数据列表.
     */
    #[Route('/recent', methods: ['GET'])]
    public function recent(): JsonResponse
    {
        $recentOrders = $this->orderRepository->findRecent(5);
        $recentExceptions = $this->orderExceptionRepository->findRecent(5);
        $pendingFulfillments = $this->fulfillmentRepository->findPendingRecent(5);

        return $this->json([
            'data' => [
                'recentOrders' => array_map(fn ($order) => $this->serializeOrder($order), $recentOrders),
                'recentExceptions' => array_map(fn ($exception) => $this->serializeException($exception), $recentExceptions),
                'pendingFulfillments' => array_map(fn ($fulfillment) => $this->serializeFulfillment($fulfillment), $pendingFulfillments),
            ],
        ]);
    }

    /**
     * 计算增长率.
     */
    private function calculateGrowth(float $current, float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * 序列化订单数据.
     *
     * @return array<string, mixed>
     */
    private function serializeOrder(Order $order): array
    {
        $channel = $order->getSalesChannel();

        return [
            'id' => $order->getId(),
            'orderNo' => $order->getOrderNo(),
            'channelName' => $channel->getName(),
            'totalAmount' => $order->getTotalAmount(),
            'status' => $order->getStatus(),
            'placedAt' => $order->getPlacedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * 序列化异常数据.
     *
     * @return array<string, mixed>
     */
    private function serializeException(OrderException $exception): array
    {
        $order = $exception->getOrder();

        return [
            'id' => $exception->getId(),
            'exceptionNo' => $exception->getExceptionNo(),
            'type' => $exception->getType(),
            'orderNo' => $order->getOrderNo(),
            'status' => $exception->getStatus(),
            'createdAt' => $exception->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * 序列化履约单数据.
     *
     * @return array<string, mixed>
     */
    private function serializeFulfillment(\App\Entity\Fulfillment $fulfillment): array
    {
        $order = $fulfillment->getOrder();

        return [
            'id' => $fulfillment->getId(),
            'fulfillmentNo' => $fulfillment->getFulfillmentNo(),
            'orderNo' => $order->getOrderNo(),
            'type' => $fulfillment->getFulfillmentType(),
            'status' => $fulfillment->getStatus(),
            'createdAt' => $fulfillment->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
