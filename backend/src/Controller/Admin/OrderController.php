<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Attribute\AdminOnly;
use App\Dto\Admin\Query\OrderListQuery;
use App\Entity\Order;
use App\Entity\OrderException;
use App\Entity\OrderItem;
use App\Repository\OrderExceptionRepository;
use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/orders')]
#[AdminOnly]
class OrderController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly OrderExceptionRepository $exceptionRepository,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(#[MapQueryString] OrderListQuery $query = new OrderListQuery()): JsonResponse
    {
        $result = $this->orderRepository->findPaginated(
            $query->getPage(),
            $query->getLimit(),
            $query->toFilters()
        );

        return $this->json([
            'items' => array_map(fn (Order $order) => $this->serializeOrder($order), $result['items']),
            'total' => $result['total'],
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
        ]);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        $order = $this->orderRepository->find($id);

        if ($order === null) {
            return $this->json(['error' => 'Order not found'], 404);
        }

        return $this->json([
            'data' => $this->serializeOrder($order, true),
        ]);
    }

    #[Route('/stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        return $this->json([
            'data' => [
                'byStatus' => $this->orderRepository->countByStatus(),
                'byPaymentStatus' => $this->orderRepository->countByPaymentStatus(),
                'byChannel' => $this->orderRepository->countBySalesChannel(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrder(Order $order, bool $detail = false): array
    {
        $salesChannel = $order->getSalesChannel();

        // 统计异常数量
        $exceptionCount = $this->exceptionRepository->count(['order' => $order]);
        $pendingExceptionCount = $this->exceptionRepository->countPendingByOrder($order);

        $data = [
            'id' => $order->getId(),
            'orderNo' => $order->getOrderNo(),
            'externalOrderNo' => $order->getExternalOrderNo(),
            'salesChannel' => [
                'id' => $salesChannel->getId(),
                'code' => $salesChannel->getCode(),
                'name' => $salesChannel->getName(),
            ],
            'status' => $order->getStatus(),
            'statusLabel' => $this->getStatusLabel($order->getStatus()),
            'paymentStatus' => $order->getPaymentStatus(),
            'paymentStatusLabel' => $this->getPaymentStatusLabel($order->getPaymentStatus()),
            'totalAmount' => $order->getTotalAmount(),
            'currency' => $order->getCurrency(),
            'receiverName' => $order->getReceiverName(),
            'receiverCity' => $order->getReceiverCity(),
            'itemCount' => $order->getItems()->count(),
            'hasExceptions' => $exceptionCount > 0,
            'exceptionCount' => $exceptionCount,
            'pendingExceptionCount' => $pendingExceptionCount,
            'placedAt' => $order->getPlacedAt()->format(\DateTimeInterface::ATOM),
            'createdAt' => $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];

        if ($detail) {
            $data['externalOrderId'] = $order->getExternalOrderId();
            $data['receiverPhone'] = $order->getReceiverPhone();
            $data['receiverProvince'] = $order->getReceiverProvince();
            $data['receiverDistrict'] = $order->getReceiverDistrict();
            $data['receiverAddress'] = $order->getReceiverAddress();
            $data['receiverPostalCode'] = $order->getReceiverPostalCode();
            $data['receiverFullAddress'] = $order->getReceiverFullAddress();
            $data['productAmount'] = $order->getProductAmount();
            $data['shippingAmount'] = $order->getShippingAmount();
            $data['discountAmount'] = $order->getDiscountAmount();
            $data['buyerRemark'] = $order->getBuyerRemark();
            $data['sellerRemark'] = $order->getSellerRemark();
            $data['allocationFailReason'] = $order->getAllocationFailReason();
            $data['paidAt'] = $order->getPaidAt()?->format(\DateTimeInterface::ATOM);
            $data['allocatedAt'] = $order->getAllocatedAt()?->format(\DateTimeInterface::ATOM);
            $data['shippedAt'] = $order->getShippedAt()?->format(\DateTimeInterface::ATOM);
            $data['deliveredAt'] = $order->getDeliveredAt()?->format(\DateTimeInterface::ATOM);
            $data['completedAt'] = $order->getCompletedAt()?->format(\DateTimeInterface::ATOM);
            $data['cancelledAt'] = $order->getCancelledAt()?->format(\DateTimeInterface::ATOM);
            $data['syncedAt'] = $order->getSyncedAt()->format(\DateTimeInterface::ATOM);
            $data['updatedAt'] = $order->getUpdatedAt()->format(\DateTimeInterface::ATOM);

            // 订单明细
            $data['items'] = array_map(
                fn (OrderItem $item) => $this->serializeOrderItem($item),
                $order->getItems()->toArray()
            );

            // 关联异常
            $exceptions = $this->exceptionRepository->findByOrder($order);
            $data['exceptions'] = array_map(
                fn (OrderException $exception) => $this->serializeException($exception),
                $exceptions
            );
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrderItem(OrderItem $item): array
    {
        return [
            'id' => $item->getId(),
            'productName' => $item->getProductName() ?? $item->getExternalProductName(),
            'productImage' => $item->getProductImage() ?? $item->getExternalProductImage(),
            'skuCode' => $item->getSkuCode(),
            'colorCode' => $item->getColorCode(),
            'sizeValue' => $item->getSizeValue(),
            'quantity' => $item->getQuantity(),
            'allocatedQuantity' => $item->getAllocatedQuantity(),
            'shippedQuantity' => $item->getShippedQuantity(),
            'unitPrice' => $item->getUnitPrice(),
            'totalPrice' => $item->getTotalPrice(),
            'discountAmount' => $item->getDiscountAmount(),
            'payableAmount' => $item->getPayableAmount(),
            'allocationStatus' => $item->getAllocationStatus(),
            'allocationStatusLabel' => $this->getAllocationStatusLabel($item->getAllocationStatus()),
            'externalProductId' => $item->getExternalProductId(),
            'externalProductName' => $item->getExternalProductName(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeException(OrderException $exception): array
    {
        return [
            'id' => $exception->getId(),
            'exceptionNo' => $exception->getExceptionNo(),
            'type' => $exception->getType(),
            'typeLabel' => $exception->getTypeLabel(),
            'status' => $exception->getStatus(),
            'statusLabel' => $exception->getStatusLabel(),
            'description' => $exception->getDescription(),
            'createdAt' => $exception->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            Order::STATUS_PENDING => '待处理',
            Order::STATUS_ALLOCATING => '分配中',
            Order::STATUS_ALLOCATED => '已分配',
            Order::STATUS_ALLOCATION_FAILED => '分配失败',
            Order::STATUS_FULFILLING => '履约中',
            Order::STATUS_SHIPPED => '已发货',
            Order::STATUS_DELIVERED => '已签收',
            Order::STATUS_COMPLETED => '已完成',
            Order::STATUS_CANCELLED => '已取消',
            default => $status,
        };
    }

    private function getPaymentStatusLabel(string $paymentStatus): string
    {
        return match ($paymentStatus) {
            Order::PAYMENT_PENDING => '待支付',
            Order::PAYMENT_PAID => '已支付',
            Order::PAYMENT_REFUNDED => '已退款',
            Order::PAYMENT_PARTIAL_REFUNDED => '部分退款',
            default => $paymentStatus,
        };
    }

    private function getAllocationStatusLabel(string $allocationStatus): string
    {
        return match ($allocationStatus) {
            OrderItem::ALLOCATION_PENDING => '待分配',
            OrderItem::ALLOCATION_PARTIAL => '部分分配',
            OrderItem::ALLOCATION_FULL => '全部分配',
            OrderItem::ALLOCATION_FAILED => '分配失败',
            default => $allocationStatus,
        };
    }
}
