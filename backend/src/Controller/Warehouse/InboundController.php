<?php

namespace App\Controller\Warehouse;

use App\Attribute\WarehouseOnly;
use App\Dto\Inbound\CompleteInboundReceivingRequest;
use App\Dto\Inbound\CreateInboundExceptionRequest;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Repository\InboundOrderRepository;
use App\Service\CosService;
use App\Service\InboundOrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 仓库用户 - 入库单管理
 */
#[Route('/api/warehouse/inbound')]
#[IsGranted('ROLE_USER')]
#[WarehouseOnly]
class InboundController extends AbstractController
{
    public function __construct(
        private InboundOrderService $inboundOrderService,
        private InboundOrderRepository $inboundOrderRepository,
        private CosService $cosService,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * 获取入库单统计数据
     */
    #[Route('/stats', name: 'warehouse_inbound_stats', methods: ['GET'])]
    public function getStats(Warehouse $warehouse): JsonResponse
    {
        $statusCounts = $this->inboundOrderRepository->countByWarehouseGroupByStatus($warehouse);
        $completedToday = $this->inboundOrderRepository->countCompletedTodayByWarehouse($warehouse);

        // 待到货: shipped 状态
        $awaitingArrival = $statusCounts['shipped'] ?? 0;

        // 待收货: arrived + receiving 状态
        $pendingReceiving = ($statusCounts['arrived'] ?? 0) + ($statusCounts['receiving'] ?? 0);

        return $this->json([
            'data' => [
                'awaitingArrival' => $awaitingArrival,
                'pendingReceiving' => $pendingReceiving,
                'completedToday' => $completedToday,
            ],
        ]);
    }

    /**
     * 获取本仓库入库单列表
     */
    #[Route('/orders', name: 'warehouse_inbound_list', methods: ['GET'])]
    public function listOrders(
        Warehouse $warehouse,
        Request $request
    ): JsonResponse {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));
        $status = $request->query->get('status');
        $orderNo = $request->query->get('orderNo');

        $filters = [];
        if ($status) {
            $filters['status'] = $status;
        }
        if ($orderNo) {
            $filters['orderNo'] = $orderNo;
        }

        $result = $this->inboundOrderRepository->findByWarehousePaginated(
            $warehouse,
            $page,
            $limit,
            $filters
        );

        return $this->json([
            'data' => array_map([$this, 'serializeOrder'], $result['data']),
            'meta' => $result['meta'],
        ]);
    }

    /**
     * 获取入库单详情
     */
    #[Route('/orders/{id}', name: 'warehouse_inbound_detail', methods: ['GET'])]
    public function getOrder(
        Warehouse $warehouse,
        string $id
    ): JsonResponse {
        $order = $this->inboundOrderService->getOrderById($id);

        if ($order === null || $order->getWarehouse()->getId() !== $warehouse->getId()) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'data' => $this->serializeOrderDetail($order),
        ]);
    }

    /**
     * 完成收货
     */
    #[Route('/orders/{id}/receive', name: 'warehouse_inbound_receive', methods: ['POST'])]
    public function receiveOrder(
        #[CurrentUser] User $user,
        Warehouse $warehouse,
        string $id,
        #[MapRequestPayload] CompleteInboundReceivingRequest $dto
    ): JsonResponse {
        $order = $this->inboundOrderService->getOrderById($id);

        if ($order === null || $order->getWarehouse()->getId() !== $warehouse->getId()) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $order = $this->inboundOrderService->completeReceiving(
                $order,
                $dto,
                $user->getId(),
                $user->getEmail()
            );

            return $this->json([
                'message' => $this->translator->trans('inbound.order.received'),
                'data' => $this->serializeOrderDetail($order),
            ]);
        } catch (\LogicException | \InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 创建异常单
     */
    #[Route('/orders/{id}/exceptions', name: 'warehouse_inbound_exception', methods: ['POST'])]
    public function createException(
        Warehouse $warehouse,
        string $id,
        #[MapRequestPayload] CreateInboundExceptionRequest $dto
    ): JsonResponse {
        $order = $this->inboundOrderService->getOrderById($id);

        if ($order === null || $order->getWarehouse()->getId() !== $warehouse->getId()) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $exception = $this->inboundOrderService->createException($order, $dto);

            return $this->json([
                'message' => $this->translator->trans('inbound.exception.created'),
                'data' => $this->serializeException($exception),
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 更新仓库备注
     */
    #[Route('/orders/{id}/notes', name: 'warehouse_inbound_notes', methods: ['PUT'])]
    public function updateNotes(
        Warehouse $warehouse,
        string $id,
        Request $request
    ): JsonResponse {
        $order = $this->inboundOrderService->getOrderById($id);

        if ($order === null || $order->getWarehouse()->getId() !== $warehouse->getId()) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $notes = $data['notes'] ?? '';

        try {
            $order = $this->inboundOrderService->updateWarehouseNotes($order, $notes);

            return $this->json([
                'message' => $this->translator->trans('inbound.order.notes_updated'),
                'data' => $this->serializeOrderDetail($order),
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 序列化入库单（列表）
     */
    private function serializeOrder($order): array
    {
        return [
            'id' => $order->getId(),
            'orderNo' => $order->getOrderNo(),
            'status' => $order->getStatus(),
            'merchant' => [
                'id' => $order->getMerchant()->getId(),
                'companyName' => $order->getMerchant()->getName(),
            ],
            'totalSkuCount' => $order->getTotalSkuCount(),
            'totalQuantity' => $order->getTotalQuantity(),
            'receivedQuantity' => $order->getReceivedQuantity(),
            'expectedArrivalDate' => $order->getExpectedArrivalDate()?->format('Y-m-d'),
            'shippedAt' => $order->getShippedAt()?->format('c'),
            'completedAt' => $order->getCompletedAt()?->format('c'),
            'createdAt' => $order->getCreatedAt()->format('c'),
        ];
    }

    /**
     * 序列化入库单（详情）
     */
    private function serializeOrderDetail($order): array
    {
        $data = $this->serializeOrder($order);

        $data['merchantNotes'] = $order->getMerchantNotes();
        $data['warehouseNotes'] = $order->getWarehouseNotes();

        $data['items'] = array_map([$this, 'serializeItem'], $order->getItems()->toArray());

        if ($order->getShipment() !== null) {
            $data['shipment'] = $this->serializeShipment($order->getShipment());
        }

        $data['exceptions'] = array_map([$this, 'serializeException'], $order->getExceptions()->toArray());

        return $data;
    }

    /**
     * 序列化入库单明细
     */
    private function serializeItem($item): array
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
            'status' => $item->getStatus(),
            'warehouseRemark' => $item->getWarehouseRemark(),
            'receivedAt' => $item->getReceivedAt()?->format('c'),
        ];
    }

    /**
     * 序列化物流信息
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
            'shippedAt' => $shipment->getShippedAt()->format('c'),
            'estimatedArrivalDate' => $shipment->getEstimatedArrivalDate()?->format('Y-m-d'),
            'deliveredAt' => $shipment->getDeliveredAt()?->format('c'),
        ];
    }

    /**
     * 序列化异常单
     */
    private function serializeException($exception): array
    {
        // 签名证据图片
        $evidenceImages = [];
        $rawImages = $exception->getEvidenceImages() ?? [];
        foreach ($rawImages as $image) {
            if ($image) {
                $cosKey = $this->extractCosKey($image);
                if ($cosKey) {
                    $evidenceImages[] = $this->cosService->getSignedUrl($cosKey, 3600);
                }
            }
        }

        return [
            'id' => $exception->getId(),
            'exceptionNo' => $exception->getExceptionNo(),
            'type' => $exception->getType(),
            'typeLabel' => $exception->getTypeLabel(),
            'status' => $exception->getStatus(),
            'items' => array_map([$this, 'serializeExceptionItem'], $exception->getItems()->toArray()),
            'totalQuantity' => $exception->getTotalQuantity(),
            'description' => $exception->getDescription(),
            'evidenceImages' => $evidenceImages,
            'resolution' => $exception->getResolution(),
            'resolutionNotes' => $exception->getResolutionNotes(),
            'resolvedAt' => $exception->getResolvedAt()?->format('c'),
            'createdAt' => $exception->getCreatedAt()->format('c'),
        ];
    }

    /**
     * 序列化异常单明细
     */
    private function serializeExceptionItem($item): array
    {
        // 签名商品图片
        $productImageUrl = null;
        $productImage = $item->getProductImage();
        if ($productImage) {
            $cosKey = $this->extractCosKey($productImage);
            if ($cosKey) {
                $productImageUrl = $this->cosService->getSignedUrl(
                    $cosKey,
                    3600,
                    'imageMogr2/thumbnail/80x80>'
                );
            }
        }

        return [
            'id' => $item->getId(),
            'skuName' => $item->getSkuName(),
            'colorName' => $item->getColorName(),
            'productName' => $item->getProductName(),
            'productImage' => $productImageUrl,
            'quantity' => $item->getQuantity(),
        ];
    }

    /**
     * 从完整 URL 或 COS key 中提取 COS key
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
