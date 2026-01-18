<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Attribute\AdminOnly;
use App\Dto\Admin\Query\OutboundOrderListQuery;
use App\Entity\OutboundOrder;
use App\Entity\OutboundOrderItem;
use App\Repository\OutboundOrderRepository;
use App\Service\CosService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/outbound')]
#[AdminOnly]
class OutboundOrderController extends AbstractController
{
    public function __construct(
        private readonly OutboundOrderRepository $outboundOrderRepository,
        private readonly CosService $cosService,
    ) {
    }

    #[Route('/orders', methods: ['GET'])]
    public function list(#[MapQueryString] OutboundOrderListQuery $query = new OutboundOrderListQuery()): JsonResponse
    {
        $result = $this->outboundOrderRepository->findPaginatedWithFilters(
            $query->getPage(),
            $query->getLimit(),
            $query->toFilters()
        );

        return $this->json([
            'items' => array_map(fn (OutboundOrder $order) => $this->serializeOrder($order), $result['items']),
            'total' => $result['total'],
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
        ]);
    }

    #[Route('/orders/{id}', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        $order = $this->outboundOrderRepository->find($id);

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
    private function serializeOrder(OutboundOrder $order): array
    {
        $merchant = $order->getMerchant();
        $warehouse = $order->getWarehouse();

        return [
            'id' => $order->getId(),
            'outboundNo' => $order->getOutboundNo(),
            'outboundType' => $order->getOutboundType(),
            'status' => $order->getStatus(),
            'syncStatus' => $order->getSyncStatus(),
            'merchant' => [
                'id' => $merchant->getId(),
                'name' => $merchant->getName(),
            ],
            'warehouse' => [
                'id' => $warehouse->getId(),
                'name' => $warehouse->getName(),
            ],
            'receiverName' => $order->getReceiverName(),
            'totalQuantity' => $order->getTotalQuantity(),
            'shippingCarrier' => $order->getShippingCarrier(),
            'trackingNumber' => $order->getTrackingNumber(),
            'shippedAt' => $order->getShippedAt()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrderDetail(OutboundOrder $order): array
    {
        $data = $this->serializeOrder($order);

        $data['externalId'] = $order->getExternalId();
        $data['receiverPhone'] = $order->getReceiverPhone();
        $data['receiverAddress'] = $order->getReceiverAddress();
        $data['receiverPostalCode'] = $order->getReceiverPostalCode();
        $data['remark'] = $order->getRemark();
        $data['cancelReason'] = $order->getCancelReason();
        $data['pickingStartedAt'] = $order->getPickingStartedAt()?->format(\DateTimeInterface::ATOM);
        $data['pickingCompletedAt'] = $order->getPickingCompletedAt()?->format(\DateTimeInterface::ATOM);
        $data['packingStartedAt'] = $order->getPackingStartedAt()?->format(\DateTimeInterface::ATOM);
        $data['packingCompletedAt'] = $order->getPackingCompletedAt()?->format(\DateTimeInterface::ATOM);
        $data['cancelledAt'] = $order->getCancelledAt()?->format(\DateTimeInterface::ATOM);

        // Fulfillment info
        $fulfillment = $order->getFulfillment();
        if ($fulfillment !== null) {
            $data['fulfillment'] = [
                'id' => $fulfillment->getId(),
                'fulfillmentNo' => $fulfillment->getFulfillmentNo(),
            ];
        } else {
            $data['fulfillment'] = null;
        }

        // Items
        $data['items'] = array_map(
            fn (OutboundOrderItem $item) => $this->serializeItem($item),
            $order->getItems()->toArray()
        );

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(OutboundOrderItem $item): array
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
            'stockType' => $item->getStockType(),
            'quantity' => $item->getQuantity(),
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
