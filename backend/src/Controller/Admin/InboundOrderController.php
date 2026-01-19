<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Attribute\AdminOnly;
use App\Dto\Admin\Query\InboundOrderListQuery;
use App\Entity\InboundException;
use App\Entity\InboundOrder;
use App\Entity\InboundOrderItem;
use App\Repository\InboundOrderRepository;
use App\Service\CosService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/inbound')]
#[AdminOnly]
class InboundOrderController extends AbstractController
{
    public function __construct(
        private readonly InboundOrderRepository $inboundOrderRepository,
        private readonly CosService $cosService,
    ) {
    }

    #[Route('/orders', methods: ['GET'])]
    public function list(#[MapQueryString] InboundOrderListQuery $query = new InboundOrderListQuery()): JsonResponse
    {
        $result = $this->inboundOrderRepository->findPaginatedWithFilters(
            $query->getPage(),
            $query->getLimit(),
            $query->toFilters()
        );

        return $this->json([
            'items' => array_map(fn (InboundOrder $order) => $this->serializeOrder($order), $result['items']),
            'total' => $result['total'],
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
        ]);
    }

    #[Route('/orders/{id}', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        $order = $this->inboundOrderRepository->find($id);

        if ($order === null) {
            return $this->json(['error' => 'Order not found'], 404);
        }

        return $this->json([
            'data' => $this->serializeOrderDetail($order),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrder(InboundOrder $order): array
    {
        $merchant = $order->getMerchant();
        $warehouse = $order->getWarehouse();

        return [
            'id' => $order->getId(),
            'orderNo' => $order->getOrderNo(),
            'status' => $order->getStatus(),
            'currency' => $order->getCurrency(),
            'merchant' => [
                'id' => $merchant->getId(),
                'name' => $merchant->getName(),
            ],
            'warehouse' => [
                'id' => $warehouse->getId(),
                'name' => $warehouse->getName(),
            ],
            'totalSkuCount' => $order->getTotalSkuCount(),
            'totalQuantity' => $order->getTotalQuantity(),
            'receivedQuantity' => $order->getReceivedQuantity(),
            'expectedArrivalDate' => $order->getExpectedArrivalDate()?->format('Y-m-d'),
            'shippedAt' => $order->getShippedAt()?->format(\DateTimeInterface::ATOM),
            'completedAt' => $order->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrderDetail(InboundOrder $order): array
    {
        $data = $this->serializeOrder($order);

        $data['merchantNotes'] = $order->getMerchantNotes();
        $data['warehouseNotes'] = $order->getWarehouseNotes();
        $data['cancelReason'] = $order->getCancelReason();
        $data['submittedAt'] = $order->getSubmittedAt()?->format(\DateTimeInterface::ATOM);
        $data['arrivedAt'] = $order->getArrivedAt()?->format(\DateTimeInterface::ATOM);
        $data['cancelledAt'] = $order->getCancelledAt()?->format(\DateTimeInterface::ATOM);

        $data['items'] = array_map(
            fn (InboundOrderItem $item) => $this->serializeItem($item),
            $order->getItems()->toArray()
        );

        if ($order->getShipment() !== null) {
            $data['shipment'] = $this->serializeShipment($order->getShipment());
        }

        $data['exceptions'] = array_map(
            fn (InboundException $exception) => $this->serializeException($exception),
            $order->getExceptions()->toArray()
        );

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(InboundOrderItem $item): array
    {
        $productImageUrl = null;
        $productImage = $item->getProductImage();
        if ($productImage) {
            $cosKey = $this->extractCosKey($productImage);
            if ($cosKey) {
                $productImageUrl = $this->cosService->getSignedUrl(
                    $cosKey,
                    3600,
                    'imageMogr2/thumbnail/120x120>'
                );
            }
        }

        return [
            'id' => $item->getId(),
            'productSku' => [
                'id' => $item->getProductSku()?->getId(),
                'skuName' => $item->getSkuName(),
                'colorName' => $item->getColorName(),
            ],
            'styleNumber' => $item->getStyleNumber(),
            'productName' => $item->getProductName(),
            'productImage' => $productImageUrl,
            'expectedQuantity' => $item->getExpectedQuantity(),
            'receivedQuantity' => $item->getReceivedQuantity(),
            'damagedQuantity' => $item->getDamagedQuantity(),
            'unitCost' => $item->getUnitCost(),
            'currency' => $item->getCurrency(),
            'status' => $item->getStatus(),
            'warehouseRemark' => $item->getWarehouseRemark(),
            'receivedAt' => $item->getReceivedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeShipment($shipment): array
    {
        return [
            'id' => $shipment->getId(),
            'carrierCode' => $shipment->getCarrierCode(),
            'carrierName' => $shipment->getCarrierName(),
            'trackingNumber' => $shipment->getTrackingNumber(),
            'status' => $shipment->getStatus(),
            'senderName' => $shipment->getSenderName(),
            'senderPhone' => $shipment->getSenderPhone(),
            'senderAddress' => $shipment->getSenderAddress(),
            'boxCount' => $shipment->getBoxCount(),
            'totalWeight' => $shipment->getTotalWeight(),
            'shippedAt' => $shipment->getShippedAt()->format(\DateTimeInterface::ATOM),
            'estimatedArrivalDate' => $shipment->getEstimatedArrivalDate()?->format('Y-m-d'),
            'deliveredAt' => $shipment->getDeliveredAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeException(InboundException $exception): array
    {
        return [
            'id' => $exception->getId(),
            'exceptionNo' => $exception->getExceptionNo(),
            'type' => $exception->getType(),
            'typeLabel' => $exception->getTypeLabel(),
            'status' => $exception->getStatus(),
            'items' => array_map(fn ($item) => [
                'id' => $item->getId(),
                'skuName' => $item->getSkuName(),
                'colorName' => $item->getColorName(),
                'productName' => $item->getProductName(),
                'productImage' => $item->getProductImage(),
                'quantity' => $item->getQuantity(),
            ], $exception->getItems()->toArray()),
            'totalQuantity' => $exception->getTotalQuantity(),
            'description' => $exception->getDescription(),
            'evidenceImages' => $exception->getEvidenceImages(),
            'resolution' => $exception->getResolution(),
            'resolutionNotes' => $exception->getResolutionNotes(),
            'resolvedAt' => $exception->getResolvedAt()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $exception->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * 从完整 URL 或 COS key 中提取 COS key.
     */
    private function extractCosKey(string $imagePathOrUrl): ?string
    {
        if (!str_starts_with($imagePathOrUrl, 'http')) {
            return $imagePathOrUrl;
        }

        $parsed = parse_url($imagePathOrUrl);
        if ($parsed && isset($parsed['path'])) {
            return ltrim($parsed['path'], '/');
        }

        return null;
    }
}
