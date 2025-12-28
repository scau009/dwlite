<?php

namespace App\Controller;

use App\Dto\Inbound\AddInboundOrderItemRequest;
use App\Dto\Inbound\CompleteInboundReceivingRequest;
use App\Dto\Inbound\CreateInboundExceptionRequest;
use App\Dto\Inbound\CreateInboundOrderRequest;
use App\Dto\Inbound\Query\InboundOrderListQuery;
use App\Dto\Inbound\ResolveInboundExceptionRequest;
use App\Dto\Inbound\ShipInboundOrderRequest;
use App\Dto\Inbound\UpdateInboundOrderItemRequest;
use App\Entity\Product;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Repository\MerchantRepository;
use App\Repository\ProductRepository;
use App\Repository\WarehouseRepository;
use App\Service\CosService;
use App\Service\InboundOrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/inbound')]
#[IsGranted('ROLE_USER')]
class InboundOrderController extends AbstractController
{
    public function __construct(
        private InboundOrderService $inboundOrderService,
        private MerchantRepository $merchantRepository,
        private WarehouseRepository $warehouseRepository,
        private ProductRepository $productRepository,
        private CosService $cosService,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * 获取当前商户
     */
    private function getCurrentMerchant(User $user)
    {
        $merchant = $this->merchantRepository->findByUser($user);
        if ($merchant === null) {
            throw $this->createAccessDeniedException('Merchant not found');
        }
        return $merchant;
    }

    /**
     * 获取可用仓库列表（平台仓库）
     */
    #[Route('/warehouses', name: 'inbound_list_warehouses', methods: ['GET'])]
    public function listWarehouses(): JsonResponse
    {
        $warehouses = $this->warehouseRepository->findActivePlatformWarehouses();

        return $this->json([
            'data' => array_map(fn(Warehouse $w) => [
                'id' => $w->getId(),
                'code' => $w->getCode(),
                'name' => $w->getName(),
                'shortName' => $w->getShortName(),
                'type' => $w->getType(),
                'fullAddress' => $w->getFullAddress(),
                'city' => $w->getCity(),
                'province' => $w->getProvince(),
            ], $warehouses),
        ]);
    }

    /**
     * 搜索商品（供入库单添加明细和商机发现使用）
     */
    #[Route('/products', name: 'inbound_search_products', methods: ['GET'])]
    public function searchProducts(Request $request): JsonResponse
    {
        $search = $request->query->get('search', '');
        $brandId = $request->query->get('brandId');
        $categoryId = $request->query->get('categoryId');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min((int) $request->query->get('limit', 20), 50);

        $filters = [
            'status' => 'active',  // 只查询已上架的商品
            'isActive' => true,
        ];

        if ($search) {
            $filters['search'] = $search;
        }
        if ($brandId) {
            $filters['brandId'] = $brandId;
        }
        if ($categoryId) {
            $filters['categoryId'] = $categoryId;
        }

        $result = $this->productRepository->findWithFilters($filters, $page, $limit);

        return $this->json([
            'data' => array_map(fn(Product $p) => $this->serializeProductForInbound($p), $result['data']),
            'meta' => $result['meta'],
        ]);
    }

    /**
     * 序列化商品（入库单用，包含 SKU 列表）
     */
    private function serializeProductForInbound(Product $product): array
    {
        $primaryImage = $product->getPrimaryImage();
        $primaryImageUrl = null;
        if ($primaryImage) {
            $primaryImageUrl = $this->cosService->getSignedUrl(
                $primaryImage->getCosKey(),
                3600,
                'imageMogr2/thumbnail/300x300>'
            );
        }

        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'styleNumber' => $product->getStyleNumber(),
            'color' => $product->getColor(),
            'primaryImageUrl' => $primaryImageUrl,
            'brandName' => $product->getBrand()?->getName(),
            'skus' => array_map(fn($sku) => [
                'id' => $sku->getId(),
                'skuName' => $sku->getSkuName(),
                'sizeUnit' => $sku->getSizeUnit()?->value,
                'sizeValue' => $sku->getSizeValue(),
                'price' => $sku->getPrice(),
                'isActive' => $sku->isActive(),
            ], $product->getSkus()->filter(fn($s) => $s->isActive())->toArray()),
        ];
    }

    /**
     * 创建入库单
     */
    #[Route('/orders', name: 'inbound_create_order', methods: ['POST'])]
    public function createOrder(
        #[CurrentUser] User $user,
        #[MapRequestPayload] CreateInboundOrderRequest $dto
    ): JsonResponse {
        $merchant = $this->getCurrentMerchant($user);

        try {
            $order = $this->inboundOrderService->createInboundOrder($merchant, $dto);

            return $this->json([
                'message' => $this->translator->trans('inbound.order.created'),
                'data' => $this->serializeOrder($order),
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 获取入库单列表
     */
    #[Route('/orders', name: 'inbound_list_orders', methods: ['GET'])]
    public function listOrders(
        #[CurrentUser] User $user,
        #[MapQueryString] InboundOrderListQuery $query
    ): JsonResponse {
        $merchant = $this->getCurrentMerchant($user);

        $orders = $this->inboundOrderService->getMerchantOrders(
            $merchant,
            $query->status,
            $query->trackingNumber,
            $query->limit
        );

        return $this->json([
            'data' => array_map([$this, 'serializeOrder'], $orders),
        ]);
    }

    /**
     * 获取入库单详情
     */
    #[Route('/orders/{id}', name: 'inbound_get_order', methods: ['GET'])]
    public function getOrder(
        #[CurrentUser] User $user,
        string $id
    ): JsonResponse {
        $merchant = $this->getCurrentMerchant($user);
        $order = $this->inboundOrderService->getOrderById($id);

        if ($order === null || $order->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'data' => $this->serializeOrderDetail($order),
        ]);
    }

    /**
     * 删除草稿入库单
     */
    #[Route('/orders/{id}', name: 'inbound_delete_order', methods: ['DELETE'])]
    public function deleteOrder(
        #[CurrentUser] User $user,
        string $id
    ): JsonResponse {
        $merchant = $this->getCurrentMerchant($user);
        $order = $this->inboundOrderService->getOrderById($id);

        if ($order === null || $order->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->inboundOrderService->deleteOrder($order);

            return $this->json([
                'message' => $this->translator->trans('inbound.order.deleted'),
            ]);
        } catch (\LogicException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 添加商品到入库单
     */
    #[Route('/orders/{id}/items', name: 'inbound_add_item', methods: ['POST'])]
    public function addItem(
        #[CurrentUser] User $user,
        string $id,
        #[MapRequestPayload] AddInboundOrderItemRequest $dto
    ): JsonResponse {
        $merchant = $this->getCurrentMerchant($user);
        $order = $this->inboundOrderService->getOrderById($id);

        if ($order === null || $order->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $item = $this->inboundOrderService->addItem($order, $dto);

            return $this->json([
                'message' => $this->translator->trans('inbound.item.added'),
                'data' => $this->serializeItem($item),
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException | \LogicException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 更新入库单明细
     */
    #[Route('/orders/items/{id}', name: 'inbound_update_item', methods: ['PUT'])]
    public function updateItem(
        #[CurrentUser] User $user,
        string $id,
        #[MapRequestPayload] UpdateInboundOrderItemRequest $dto
    ): JsonResponse {
        $merchant = $this->getCurrentMerchant($user);
        $item = $this->inboundOrderService->getItemById($id);

        if ($item === null || $item->getInboundOrder()->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => 'Item not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $item = $this->inboundOrderService->updateItem($item, $dto);

            return $this->json([
                'message' => $this->translator->trans('inbound.item.updated'),
                'data' => $this->serializeItem($item),
            ]);
        } catch (\LogicException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 删除入库单明细
     */
    #[Route('/orders/items/{id}', name: 'inbound_remove_item', methods: ['DELETE'])]
    public function removeItem(
        #[CurrentUser] User $user,
        string $id
    ): JsonResponse {
        $merchant = $this->getCurrentMerchant($user);
        $item = $this->inboundOrderService->getItemById($id);

        if ($item === null || $item->getInboundOrder()->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => 'Item not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->inboundOrderService->removeItem($item);

            return $this->json([
                'message' => $this->translator->trans('inbound.item.removed'),
            ]);
        } catch (\LogicException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 提交入库单
     */
    #[Route('/orders/{id}/submit', name: 'inbound_submit_order', methods: ['POST'])]
    public function submitOrder(
        #[CurrentUser] User $user,
        string $id
    ): JsonResponse {
        $merchant = $this->getCurrentMerchant($user);
        $order = $this->inboundOrderService->getOrderById($id);

        if ($order === null || $order->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $order = $this->inboundOrderService->submitOrder($order);

            return $this->json([
                'message' => $this->translator->trans('inbound.order.submitted'),
                'data' => $this->serializeOrder($order),
            ]);
        } catch (\LogicException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 发货
     */
    #[Route('/orders/{id}/ship', name: 'inbound_ship_order', methods: ['POST'])]
    public function shipOrder(
        #[CurrentUser] User $user,
        string $id,
        #[MapRequestPayload] ShipInboundOrderRequest $dto
    ): JsonResponse {
        $merchant = $this->getCurrentMerchant($user);
        $order = $this->inboundOrderService->getOrderById($id);

        if ($order === null || $order->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $order = $this->inboundOrderService->shipOrder(
                $order,
                $dto,
                $user->getId(),
                $user->getEmail()
            );

            return $this->json([
                'message' => $this->translator->trans('inbound.order.shipped'),
                'data' => $this->serializeOrderDetail($order),
            ]);
        } catch (\LogicException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 完成收货（仓库操作）
     */
    #[Route('/orders/{id}/receive', name: 'inbound_receive_order', methods: ['POST'])]
    public function receiveOrder(
        #[CurrentUser] User $user,
        string $id,
        #[MapRequestPayload] CompleteInboundReceivingRequest $dto
    ): JsonResponse {
        // TODO: 这个接口应该限制为仓库操作员角色
        $order = $this->inboundOrderService->getOrderById($id);

        if ($order === null) {
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
     * 取消入库单
     */
    #[Route('/orders/{id}/cancel', name: 'inbound_cancel_order', methods: ['POST'])]
    public function cancelOrder(
        #[CurrentUser] User $user,
        string $id,
        Request $request
    ): JsonResponse {
        $merchant = $this->getCurrentMerchant($user);
        $order = $this->inboundOrderService->getOrderById($id);

        if ($order === null || $order->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $reason = $data['reason'] ?? 'Cancelled by merchant';

        try {
            $order = $this->inboundOrderService->cancelOrder(
                $order,
                $reason,
                $user->getId(),
                $user->getEmail()
            );

            return $this->json([
                'message' => $this->translator->trans('inbound.order.cancelled'),
                'data' => $this->serializeOrder($order),
            ]);
        } catch (\LogicException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 创建异常单
     */
    #[Route('/orders/{id}/exceptions', name: 'inbound_create_exception', methods: ['POST'])]
    public function createException(
        #[CurrentUser] User $user,
        string $id,
        #[MapRequestPayload] CreateInboundExceptionRequest $dto
    ): JsonResponse {
        // TODO: 这个接口应该限制为仓库操作员角色
        $order = $this->inboundOrderService->getOrderById($id);

        if ($order === null) {
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
     * 处理异常单
     */
    #[Route('/exceptions/{id}/resolve', name: 'inbound_resolve_exception', methods: ['POST'])]
    public function resolveException(
        #[CurrentUser] User $user,
        string $id,
        #[MapRequestPayload] ResolveInboundExceptionRequest $dto
    ): JsonResponse {
        $merchant = $this->getCurrentMerchant($user);
        $exception = $this->inboundOrderService->getExceptionById($id);

        if ($exception === null || $exception->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => 'Exception not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $exception = $this->inboundOrderService->resolveException($exception, $dto);

            return $this->json([
                'message' => $this->translator->trans('inbound.exception.resolved'),
                'data' => $this->serializeException($exception),
            ]);
        } catch (\LogicException $e) {
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
            'warehouse' => [
                'id' => $order->getWarehouse()->getId(),
                'name' => $order->getWarehouse()->getName(),
            ],
            'totalSkuCount' => $order->getTotalSkuCount(),
            'totalQuantity' => $order->getTotalQuantity(),
            'receivedQuantity' => $order->getReceivedQuantity(),
            'expectedArrivalDate' => $order->getExpectedArrivalDate()?->format('Y-m-d'),
            'submittedAt' => $order->getSubmittedAt()?->format('Y-m-d H:i:s'),
            'shippedAt' => $order->getShippedAt()?->format('Y-m-d H:i:s'),
            'completedAt' => $order->getCompletedAt()?->format('Y-m-d H:i:s'),
            'createdAt' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
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
        $data['cancelReason'] = $order->getCancelReason();

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
            // 如果是完整 URL，提取 COS key
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
            'receivedAt' => $item->getReceivedAt()?->format('Y-m-d H:i:s'),
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
            'shippedAt' => $shipment->getShippedAt()->format('Y-m-d H:i:s'),
            'estimatedArrivalDate' => $shipment->getEstimatedArrivalDate()?->format('Y-m-d'),
            'deliveredAt' => $shipment->getDeliveredAt()?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * 序列化异常单
     */
    private function serializeException($exception): array
    {
        return [
            'id' => $exception->getId(),
            'exceptionNo' => $exception->getExceptionNo(),
            'type' => $exception->getType(),
            'typeLabel' => $exception->getTypeLabel(),
            'status' => $exception->getStatus(),
            'items' => array_map([$this, 'serializeExceptionItem'], $exception->getItems()->toArray()),
            'totalQuantity' => $exception->getTotalQuantity(),
            'description' => $exception->getDescription(),
            'evidenceImages' => $exception->getEvidenceImages(),
            'resolution' => $exception->getResolution(),
            'resolutionNotes' => $exception->getResolutionNotes(),
            'resolvedAt' => $exception->getResolvedAt()?->format('Y-m-d H:i:s'),
            'createdAt' => $exception->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * 序列化异常单明细
     */
    private function serializeExceptionItem($item): array
    {
        return [
            'id' => $item->getId(),
            'skuName' => $item->getSkuName(),
            'colorName' => $item->getColorName(),
            'productName' => $item->getProductName(),
            'productImage' => $item->getProductImage(),
            'quantity' => $item->getQuantity(),
        ];
    }

    /**
     * 从完整 URL 或 COS key 中提取 COS key
     */
    private function extractCosKey(string $imagePathOrUrl): ?string
    {
        // 如果已经是相对路径（COS key），直接返回
        if (!str_starts_with($imagePathOrUrl, 'http')) {
            return $imagePathOrUrl;
        }

        // 从完整 URL 中提取 COS key
        // URL 格式: https://bucket.cos.region.myqcloud.com/dwlite/...
        $parsed = parse_url($imagePathOrUrl);
        if ($parsed && isset($parsed['path'])) {
            // 去掉开头的斜杠
            return ltrim($parsed['path'], '/');
        }

        return null;
    }
}