<?php

namespace App\Controller;

use App\Dto\Outbound\AddOutboundItemRequest;
use App\Dto\Outbound\CreateOutboundOrderRequest;
use App\Dto\Outbound\Query\OutboundOrderListQuery;
use App\Entity\InventoryTransaction;
use App\Entity\OutboundOrder;
use App\Entity\OutboundOrderItem;
use App\Entity\User;
use App\Repository\MerchantInventoryRepository;
use App\Repository\MerchantRepository;
use App\Repository\OutboundOrderRepository;
use App\Repository\WarehouseRepository;
use App\Service\CosService;
use App\Service\InventoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/outbound')]
#[IsGranted('ROLE_USER')]
class MerchantOutboundController extends AbstractController
{
    public function __construct(
        private MerchantRepository $merchantRepository,
        private OutboundOrderRepository $outboundOrderRepository,
        private WarehouseRepository $warehouseRepository,
        private MerchantInventoryRepository $inventoryRepository,
        private InventoryService $inventoryService,
        private CosService $cosService,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * 获取当前商户.
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
     * 获取出库类型选项.
     */
    #[Route('/type-options', name: 'outbound_type_options', methods: ['GET'])]
    public function getTypeOptions(): JsonResponse
    {
        return $this->json([
            'data' => [
                ['value' => OutboundOrder::TYPE_SALES, 'label' => $this->translator->trans('outbound.type.sales')],
                ['value' => OutboundOrder::TYPE_RETURN_TO_MERCHANT, 'label' => $this->translator->trans('outbound.type.return_to_merchant')],
                ['value' => OutboundOrder::TYPE_TRANSFER, 'label' => $this->translator->trans('outbound.type.transfer')],
                ['value' => OutboundOrder::TYPE_SCRAP, 'label' => $this->translator->trans('outbound.type.scrap')],
            ],
        ]);
    }

    /**
     * 获取出库状态选项.
     */
    #[Route('/status-options', name: 'outbound_status_options', methods: ['GET'])]
    public function getStatusOptions(): JsonResponse
    {
        return $this->json([
            'data' => [
                ['value' => OutboundOrder::STATUS_DRAFT, 'label' => $this->translator->trans('outbound.status.draft')],
                ['value' => OutboundOrder::STATUS_PENDING, 'label' => $this->translator->trans('outbound.status.pending')],
                ['value' => OutboundOrder::STATUS_PICKING, 'label' => $this->translator->trans('outbound.status.picking')],
                ['value' => OutboundOrder::STATUS_PACKING, 'label' => $this->translator->trans('outbound.status.packing')],
                ['value' => OutboundOrder::STATUS_READY, 'label' => $this->translator->trans('outbound.status.ready')],
                ['value' => OutboundOrder::STATUS_SHIPPED, 'label' => $this->translator->trans('outbound.status.shipped')],
                ['value' => OutboundOrder::STATUS_CANCELLED, 'label' => $this->translator->trans('outbound.status.cancelled')],
            ],
        ]);
    }

    /**
     * 出库单列表.
     */
    #[Route('/orders', name: 'outbound_list_orders', methods: ['GET'])]
    public function listOrders(
        #[CurrentUser] User $user,
        #[MapQueryString] ?OutboundOrderListQuery $query = null
    ): JsonResponse {
        $query = $query ?? new OutboundOrderListQuery();
        $merchant = $this->getCurrentMerchant($user);

        $result = $this->outboundOrderRepository->findByMerchantPaginated(
            $merchant,
            $query->status,
            $query->outboundType,
            $query->warehouseId,
            $query->outboundNo,
            $query->trackingNumber,
            $query->page,
            $query->limit
        );

        return $this->json([
            'data' => array_map(fn (OutboundOrder $order) => $this->formatOutboundOrder($order), $result['items']),
            'meta' => [
                'total' => $result['total'],
                'page' => $query->page,
                'limit' => $query->limit,
                'totalPages' => (int) ceil($result['total'] / $query->limit),
            ],
        ]);
    }

    /**
     * 出库单详情.
     */
    #[Route('/orders/{id}', name: 'outbound_get_order', methods: ['GET'])]
    public function getOrder(
        string $id,
        #[CurrentUser] User $user
    ): JsonResponse {
        $merchant = $this->getCurrentMerchant($user);
        $order = $this->outboundOrderRepository->findOneByIdAndMerchant($id, $merchant);

        if ($order === null) {
            return $this->json([
                'error' => $this->translator->trans('outbound.order_not_found'),
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'data' => $this->formatOutboundOrderDetail($order),
        ]);
    }

    /**
     * 创建出库单（草稿状态）.
     */
    #[Route('/orders', name: 'outbound_create_order', methods: ['POST'])]
    public function createOrder(
        #[CurrentUser] User $user,
        #[MapRequestPayload] CreateOutboundOrderRequest $request
    ): JsonResponse {
        $merchant = $this->getCurrentMerchant($user);

        // 验证仓库
        $warehouse = $this->warehouseRepository->find($request->warehouseId);
        if ($warehouse === null) {
            return $this->json([
                'error' => $this->translator->trans('validation.warehouse_not_found'),
            ], Response::HTTP_BAD_REQUEST);
        }

        // 创建草稿出库单
        $outboundOrder = new OutboundOrder();
        $outboundOrder->setMerchant($merchant);
        $outboundOrder->setWarehouse($warehouse);
        $outboundOrder->setOutboundType(OutboundOrder::TYPE_RETURN_TO_MERCHANT);
        $outboundOrder->setReceiverName($request->receiverName);
        $outboundOrder->setReceiverPhone($request->receiverPhone);
        $outboundOrder->setReceiverAddress($request->receiverAddress);
        $outboundOrder->setReceiverPostalCode($request->receiverPostalCode);
        $outboundOrder->setRemark($request->remark);

        $this->entityManager->persist($outboundOrder);
        $this->entityManager->flush();

        return $this->json([
            'message' => $this->translator->trans('outbound.order_created'),
            'data' => $this->formatOutboundOrder($outboundOrder),
        ], Response::HTTP_CREATED);
    }

    /**
     * 删除出库单（仅草稿状态可删除）.
     */
    #[Route('/orders/{id}', name: 'outbound_delete_order', methods: ['DELETE'])]
    public function deleteOrder(
        string $id,
        #[CurrentUser] User $user
    ): JsonResponse {
        $merchant = $this->getCurrentMerchant($user);
        $order = $this->outboundOrderRepository->findOneByIdAndMerchant($id, $merchant);

        if ($order === null) {
            return $this->json([
                'error' => $this->translator->trans('outbound.order_not_found'),
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$order->isDraft()) {
            return $this->json([
                'error' => $this->translator->trans('outbound.only_draft_can_delete'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->remove($order);
        $this->entityManager->flush();

        return $this->json([
            'message' => $this->translator->trans('outbound.order_deleted'),
        ]);
    }

    /**
     * 提交出库单（草稿 → 待处理）.
     */
    #[Route('/orders/{id}/submit', name: 'outbound_submit_order', methods: ['POST'])]
    public function submitOrder(
        string $id,
        #[CurrentUser] User $user
    ): JsonResponse {
        $merchant = $this->getCurrentMerchant($user);
        $order = $this->outboundOrderRepository->findOneByIdAndMerchant($id, $merchant);

        if ($order === null) {
            return $this->json([
                'error' => $this->translator->trans('outbound.order_not_found'),
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$order->isDraft()) {
            return $this->json([
                'error' => $this->translator->trans('outbound.only_draft_can_submit'),
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($order->getItems()->isEmpty()) {
            return $this->json([
                'error' => $this->translator->trans('outbound.no_items_to_submit'),
            ], Response::HTTP_BAD_REQUEST);
        }

        // 锁定所有商品的库存
        foreach ($order->getItems() as $item) {
            $inventory = $this->inventoryRepository->findOneBy([
                'merchant' => $merchant,
                'warehouse' => $order->getWarehouse(),
                'productSku' => $item->getProductSku(),
            ]);

            if ($inventory === null) {
                return $this->json([
                    'error' => $this->translator->trans('validation.inventory_not_found'),
                ], Response::HTTP_BAD_REQUEST);
            }

            // 根据库存类型验证可用库存
            $isDamagedStock = $item->isDamagedStock();
            $availableQuantity = $isDamagedStock
                ? $inventory->getQuantityDamaged()
                : $inventory->getQuantityAvailable();

            if ($availableQuantity < $item->getQuantity()) {
                return $this->json([
                    'error' => $this->translator->trans('validation.insufficient_stock', [
                        '%sku%' => $item->getStyleNumber().'-'.$item->getSkuName(),
                        '%available%' => $availableQuantity,
                        '%requested%' => $item->getQuantity(),
                    ]),
                ], Response::HTTP_BAD_REQUEST);
            }

            // 根据库存类型锁定库存
            if ($isDamagedStock) {
                $this->inventoryService->reserveDamagedStock(
                    $inventory,
                    $item->getQuantity(),
                    InventoryTransaction::REF_OUTBOUND_ORDER,
                    $order->getId(),
                    $order->getOutboundNo(),
                    $user->getId(),
                    $user->getEmail()
                );
            } else {
                $this->inventoryService->reserveStock(
                    $inventory,
                    $item->getQuantity(),
                    InventoryTransaction::REF_OUTBOUND_ORDER,
                    $order->getId(),
                    $order->getOutboundNo(),
                    $user->getId(),
                    $user->getEmail()
                );
            }
        }

        $order->submit();
        $this->entityManager->flush();

        return $this->json([
            'message' => $this->translator->trans('outbound.order_submitted'),
            'data' => $this->formatOutboundOrder($order),
        ]);
    }

    /**
     * 添加出库单商品
     */
    #[Route('/orders/{id}/items', name: 'outbound_add_item', methods: ['POST'])]
    public function addItem(
        string $id,
        #[CurrentUser] User $user,
        #[MapRequestPayload] AddOutboundItemRequest $request
    ): JsonResponse {
        $merchant = $this->getCurrentMerchant($user);
        $order = $this->outboundOrderRepository->findOneByIdAndMerchant($id, $merchant);

        if ($order === null) {
            return $this->json([
                'error' => $this->translator->trans('outbound.order_not_found'),
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$order->isDraft()) {
            return $this->json([
                'error' => $this->translator->trans('outbound.only_draft_can_edit'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $inventory = $this->inventoryRepository->find($request->inventoryId);
        if ($inventory === null) {
            return $this->json([
                'error' => $this->translator->trans('validation.inventory_not_found'),
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($inventory->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json([
                'error' => $this->translator->trans('validation.inventory_not_owned'),
            ], Response::HTTP_FORBIDDEN);
        }

        if ($inventory->getWarehouse()->getId() !== $order->getWarehouse()->getId()) {
            return $this->json([
                'error' => $this->translator->trans('validation.inventory_warehouse_mismatch'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $sku = $inventory->getProductSku();
        $product = $sku->getProduct();
        $skuIdentifier = $product->getStyleNumber().'-'.$sku->getSizeValue();

        // 根据库存类型验证可用库存
        $isDamagedStock = $request->stockType === OutboundOrderItem::STOCK_TYPE_DAMAGED;
        $availableQuantity = $isDamagedStock
            ? $inventory->getQuantityDamaged()
            : $inventory->getQuantityAvailable();

        if ($availableQuantity < $request->quantity) {
            return $this->json([
                'error' => $this->translator->trans('validation.insufficient_stock', [
                    '%sku%' => $skuIdentifier,
                    '%available%' => $availableQuantity,
                    '%requested%' => $request->quantity,
                ]),
            ], Response::HTTP_BAD_REQUEST);
        }

        // 检查是否已存在该商品（通过 SKU ID + 库存类型 比较）
        foreach ($order->getItems() as $existingItem) {
            // 比较 styleNumber + skuName + stockType 组合
            if ($existingItem->getStyleNumber() === $product->getStyleNumber()
                && $existingItem->getSkuName() === $sku->getSizeValue()
                && $existingItem->getStockType() === $request->stockType) {
                return $this->json([
                    'error' => $this->translator->trans('outbound.item_already_exists'),
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // 创建出库单明细
        $item = new OutboundOrderItem();
        $item->setOutboundOrder($order);
        $item->setMerchant($merchant);
        $item->setWarehouse($order->getWarehouse());
        $item->setProductSku($sku);
        $item->setQuantity($request->quantity);
        $item->setStockType($request->stockType);
        $item->snapshotFromSku($sku);

        $order->addItem($item);
        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $this->json([
            'message' => $this->translator->trans('outbound.item_added'),
            'data' => $this->formatOutboundOrderDetail($order),
        ], Response::HTTP_CREATED);
    }

    /**
     * 删除出库单商品
     */
    #[Route('/orders/{orderId}/items/{itemId}', name: 'outbound_remove_item', methods: ['DELETE'])]
    public function removeItem(
        string $orderId,
        string $itemId,
        #[CurrentUser] User $user
    ): JsonResponse {
        $merchant = $this->getCurrentMerchant($user);
        $order = $this->outboundOrderRepository->findOneByIdAndMerchant($orderId, $merchant);

        if ($order === null) {
            return $this->json([
                'error' => $this->translator->trans('outbound.order_not_found'),
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$order->isDraft()) {
            return $this->json([
                'error' => $this->translator->trans('outbound.only_draft_can_edit'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $item = null;
        foreach ($order->getItems() as $existingItem) {
            if ($existingItem->getId() === $itemId) {
                $item = $existingItem;
                break;
            }
        }

        if ($item === null) {
            return $this->json([
                'error' => $this->translator->trans('outbound.item_not_found'),
            ], Response::HTTP_NOT_FOUND);
        }

        $order->removeItem($item);
        $this->entityManager->remove($item);
        $this->entityManager->flush();

        return $this->json([
            'message' => $this->translator->trans('outbound.item_removed'),
            'data' => $this->formatOutboundOrderDetail($order),
        ]);
    }

    /**
     * 格式化出库单（列表）.
     */
    private function formatOutboundOrder(OutboundOrder $order): array
    {
        $warehouse = $order->getWarehouse();

        return [
            'id' => $order->getId(),
            'outboundNo' => $order->getOutboundNo(),
            'outboundType' => $order->getOutboundType(),
            'outboundTypeLabel' => $this->getTypeLabel($order->getOutboundType()),
            'status' => $order->getStatus(),
            'statusLabel' => $this->getStatusLabel($order->getStatus()),
            'warehouse' => [
                'id' => $warehouse->getId(),
                'name' => $warehouse->getName(),
                'shortName' => $warehouse->getShortName(),
            ],
            'receiverName' => $order->getReceiverName(),
            'receiverPhone' => $order->getReceiverPhone(),
            'shippingCarrier' => $order->getShippingCarrier(),
            'trackingNumber' => $order->getTrackingNumber(),
            'totalQuantity' => $order->getTotalQuantity(),
            'shippedAt' => $this->formatUtcIso8601($order->getShippedAt()),
            'createdAt' => $this->formatUtcIso8601($order->getCreatedAt()),
        ];
    }

    /**
     * 格式化出库单（详情）.
     */
    private function formatOutboundOrderDetail(OutboundOrder $order): array
    {
        $data = $this->formatOutboundOrder($order);
        $data['receiverAddress'] = $order->getReceiverAddress();
        $data['receiverPostalCode'] = $order->getReceiverPostalCode();
        $data['remark'] = $order->getRemark();
        $data['cancelReason'] = $order->getCancelReason();
        $data['pickingStartedAt'] = $this->formatUtcIso8601($order->getPickingStartedAt());
        $data['pickingCompletedAt'] = $this->formatUtcIso8601($order->getPickingCompletedAt());
        $data['packingStartedAt'] = $this->formatUtcIso8601($order->getPackingStartedAt());
        $data['packingCompletedAt'] = $this->formatUtcIso8601($order->getPackingCompletedAt());
        $data['cancelledAt'] = $this->formatUtcIso8601($order->getCancelledAt());
        $data['updatedAt'] = $this->formatUtcIso8601($order->getUpdatedAt());

        // 关联订单信息
        $fulfillment = $order->getFulfillment();
        if ($fulfillment !== null) {
            $salesOrder = $fulfillment->getOrder();
            $data['relatedOrder'] = [
                'id' => $salesOrder->getId(),
                'orderNo' => $salesOrder->getOrderNo(),
            ];
        }

        // 出库单明细
        $data['items'] = array_map(function (OutboundOrderItem $item) {
            // 签名图片 URL
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
                'skuName' => $item->getSkuName(),
                'styleNumber' => $item->getStyleNumber(),
                'colorName' => $item->getColorName(),
                'productName' => $item->getProductName(),
                'productImage' => $productImageUrl,
                'stockType' => $item->getStockType(),
                'quantity' => $item->getQuantity(),
            ];
        }, $order->getItems()->toArray());

        return $data;
    }

    private function formatUtcIso8601(?\DateTimeImmutable $dateTime): ?string
    {
        if ($dateTime === null) {
            return null;
        }

        return $dateTime->setTimezone(new \DateTimeZone('UTC'))->format('c');
    }

    /**
     * 从图片路径或 URL 中提取 COS key.
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

    /**
     * 获取出库类型标签.
     */
    private function getTypeLabel(string $type): string
    {
        return match ($type) {
            OutboundOrder::TYPE_SALES => $this->translator->trans('outbound.type.sales'),
            OutboundOrder::TYPE_RETURN_TO_MERCHANT => $this->translator->trans('outbound.type.return_to_merchant'),
            OutboundOrder::TYPE_TRANSFER => $this->translator->trans('outbound.type.transfer'),
            OutboundOrder::TYPE_SCRAP => $this->translator->trans('outbound.type.scrap'),
            default => $type,
        };
    }

    /**
     * 获取状态标签.
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            OutboundOrder::STATUS_DRAFT => $this->translator->trans('outbound.status.draft'),
            OutboundOrder::STATUS_PENDING => $this->translator->trans('outbound.status.pending'),
            OutboundOrder::STATUS_PICKING => $this->translator->trans('outbound.status.picking'),
            OutboundOrder::STATUS_PACKING => $this->translator->trans('outbound.status.packing'),
            OutboundOrder::STATUS_READY => $this->translator->trans('outbound.status.ready'),
            OutboundOrder::STATUS_SHIPPED => $this->translator->trans('outbound.status.shipped'),
            OutboundOrder::STATUS_CANCELLED => $this->translator->trans('outbound.status.cancelled'),
            default => $status,
        };
    }
}
