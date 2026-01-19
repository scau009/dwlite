<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Attribute\AdminOnly;
use App\Dto\Admin\Query\FulfillmentListQuery;
use App\Entity\Fulfillment;
use App\Entity\FulfillmentItem;
use App\Repository\FulfillmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/fulfillments')]
#[AdminOnly]
class FulfillmentController extends AbstractController
{
    public function __construct(
        private readonly FulfillmentRepository $fulfillmentRepository,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(#[MapQueryString] FulfillmentListQuery $query = new FulfillmentListQuery()): JsonResponse
    {
        $result = $this->fulfillmentRepository->findPaginatedWithFilters(
            $query->getPage(),
            $query->getLimit(),
            $query->toFilters()
        );

        return $this->json([
            'items' => array_map(fn (Fulfillment $f) => $this->serializeFulfillment($f), $result['items']),
            'total' => $result['total'],
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
        ]);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        $fulfillment = $this->fulfillmentRepository->find($id);

        if ($fulfillment === null) {
            return $this->json(['error' => 'Fulfillment not found'], 404);
        }

        return $this->json([
            'data' => $this->serializeFulfillment($fulfillment, true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeFulfillment(Fulfillment $fulfillment, bool $detail = false): array
    {
        $order = $fulfillment->getOrder();
        $merchant = $fulfillment->getMerchant();
        $warehouse = $fulfillment->getWarehouse();

        $data = [
            'id' => $fulfillment->getId(),
            'fulfillmentNo' => $fulfillment->getFulfillmentNo(),
            'fulfillmentType' => $fulfillment->getFulfillmentType(),
            'fulfillmentTypeLabel' => $this->getFulfillmentTypeLabel($fulfillment->getFulfillmentType()),
            'status' => $fulfillment->getStatus(),
            'statusLabel' => $this->getStatusLabel($fulfillment->getStatus()),
            'order' => [
                'id' => $order->getId(),
                'orderNo' => $order->getOrderNo(),
            ],
            'merchant' => $merchant !== null ? [
                'id' => $merchant->getId(),
                'name' => $merchant->getName(),
            ] : null,
            'warehouse' => [
                'id' => $warehouse->getId(),
                'name' => $warehouse->getName(),
            ],
            'itemCount' => $fulfillment->getItems()->count(),
            'totalQuantity' => $fulfillment->getTotalQuantity(),
            'shippingCarrier' => $fulfillment->getShippingCarrier(),
            'trackingNumber' => $fulfillment->getTrackingNumber(),
            'deadlineAt' => $fulfillment->getDeadlineAt()?->format(\DateTimeInterface::ATOM),
            'isOverdue' => $fulfillment->isOverdue(),
            'createdAt' => $fulfillment->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'shippedAt' => $fulfillment->getShippedAt()?->format(\DateTimeInterface::ATOM),
        ];

        if ($detail) {
            $data['trackingUrl'] = $fulfillment->getTrackingUrl();
            $data['allocationSource'] = $fulfillment->getAllocationSource();
            $data['allocationSourceLabel'] = $this->getAllocationSourceLabel($fulfillment->getAllocationSource());
            $data['allocationAttempt'] = $fulfillment->getAllocationAttempt();
            $data['notifiedAt'] = $fulfillment->getNotifiedAt()?->format(\DateTimeInterface::ATOM);
            $data['deliveredAt'] = $fulfillment->getDeliveredAt()?->format(\DateTimeInterface::ATOM);
            $data['completedAt'] = $fulfillment->getCompletedAt()?->format(\DateTimeInterface::ATOM);
            $data['cancelledAt'] = $fulfillment->getCancelledAt()?->format(\DateTimeInterface::ATOM);
            $data['cancelReason'] = $fulfillment->getCancelReason();
            $data['rejectedAt'] = $fulfillment->getRejectedAt()?->format(\DateTimeInterface::ATOM);
            $data['rejectionReason'] = $fulfillment->getRejectionReason();
            $data['remark'] = $fulfillment->getRemark();
            $data['updatedAt'] = $fulfillment->getUpdatedAt()->format(\DateTimeInterface::ATOM);

            // Order detail
            $data['order']['externalOrderNo'] = $order->getExternalOrderNo();
            $data['order']['status'] = $order->getStatus();
            $data['order']['totalAmount'] = $order->getTotalAmount();
            $data['order']['currency'] = $order->getCurrency();
            $data['order']['receiverName'] = $order->getReceiverName();
            $data['order']['receiverCity'] = $order->getReceiverCity();
            $data['order']['receiverFullAddress'] = $order->getReceiverFullAddress();

            // Items
            $data['items'] = array_map(
                fn (FulfillmentItem $item) => $this->serializeFulfillmentItem($item),
                $fulfillment->getItems()->toArray()
            );

            // Outbound order info (for platform warehouse)
            $outboundOrder = $fulfillment->getOutboundOrder();
            $data['outboundOrder'] = $outboundOrder !== null ? [
                'id' => $outboundOrder->getId(),
                'orderNo' => $outboundOrder->getOutboundNo(),
                'status' => $outboundOrder->getStatus(),
                'pickingStartedAt' => $outboundOrder->getPickingStartedAt()?->format(\DateTimeInterface::ATOM),
                'pickingCompletedAt' => $outboundOrder->getPickingCompletedAt()?->format(\DateTimeInterface::ATOM),
                'packingStartedAt' => $outboundOrder->getPackingStartedAt()?->format(\DateTimeInterface::ATOM),
                'packingCompletedAt' => $outboundOrder->getPackingCompletedAt()?->format(\DateTimeInterface::ATOM),
                'shippedAt' => $outboundOrder->getShippedAt()?->format(\DateTimeInterface::ATOM),
                'cancelledAt' => $outboundOrder->getCancelledAt()?->format(\DateTimeInterface::ATOM),
            ] : null;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeFulfillmentItem(FulfillmentItem $item): array
    {
        $orderItem = $item->getOrderItem();
        $merchant = $item->getMerchant();
        $warehouse = $item->getWarehouse();

        return [
            'id' => $item->getId(),
            'quantity' => $item->getQuantity(),
            'listPrice' => $item->getListPrice(),
            'settlementPrice' => $item->getSettlementPrice(),
            'commissionRate' => $item->getCommissionRate(),
            'commissionAmount' => $item->getCommissionAmount(),
            'settlementTotal' => $item->getSettlementTotal(),
            'orderItem' => [
                'id' => $orderItem->getId(),
                'productName' => $orderItem->getProductName() ?? $orderItem->getExternalProductName(),
                'productImage' => $orderItem->getProductImage() ?? $orderItem->getExternalProductImage(),
                'skuCode' => $orderItem->getSkuCode(),
                'colorCode' => $orderItem->getColorCode(),
                'sizeValue' => $orderItem->getSizeValue(),
                'quantity' => $orderItem->getQuantity(),
                'unitPrice' => $orderItem->getUnitPrice(),
            ],
            'merchant' => $merchant !== null ? [
                'id' => $merchant->getId(),
                'name' => $merchant->getName(),
            ] : null,
            'warehouse' => $warehouse !== null ? [
                'id' => $warehouse->getId(),
                'name' => $warehouse->getName(),
            ] : null,
            'createdAt' => $item->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function getFulfillmentTypeLabel(string $type): string
    {
        return match ($type) {
            Fulfillment::TYPE_PLATFORM_WAREHOUSE => '平台仓发货',
            Fulfillment::TYPE_MERCHANT_WAREHOUSE => '商家自发货',
            default => $type,
        };
    }

    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            Fulfillment::STATUS_PENDING => '待处理',
            Fulfillment::STATUS_PROCESSING => '处理中',
            Fulfillment::STATUS_SHIPPED => '已发货',
            Fulfillment::STATUS_DELIVERED => '已签收',
            Fulfillment::STATUS_COMPLETED => '已完成',
            Fulfillment::STATUS_CANCELLED => '已取消',
            Fulfillment::STATUS_REJECTED => '已拒绝',
            Fulfillment::STATUS_EXPIRED => '已超时',
            default => $status,
        };
    }

    private function getAllocationSourceLabel(?string $source): ?string
    {
        if ($source === null) {
            return null;
        }

        return match ($source) {
            Fulfillment::ALLOCATION_SOURCE_AUTO => '系统自动分配',
            Fulfillment::ALLOCATION_SOURCE_MANUAL => '人工手动分配',
            default => $source,
        };
    }
}
