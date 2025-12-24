<?php

namespace App\Controller\Warehouse;

use App\Attribute\WarehouseOnly;
use App\Entity\OutboundOrder;
use App\Entity\User;
use App\Repository\OutboundOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 仓库用户 - 出库单管理
 */
#[Route('/api/warehouse/outbound')]
#[IsGranted('ROLE_USER')]
#[WarehouseOnly]
class OutboundController extends AbstractController
{
    public function __construct(
        private OutboundOrderRepository $outboundOrderRepository,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * 获取本仓库出库单列表
     */
    #[Route('/orders', name: 'warehouse_outbound_list', methods: ['GET'])]
    public function listOrders(
        #[CurrentUser] User $user,
        Request $request
    ): JsonResponse {
        $warehouse = $user->getWarehouse();
        if ($warehouse === null) {
            return $this->json(['error' => 'No warehouse assigned'], Response::HTTP_FORBIDDEN);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));
        $status = $request->query->get('status');

        $result = $this->findByWarehousePaginated($warehouse, $page, $limit, $status);

        return $this->json([
            'data' => array_map([$this, 'serializeOrder'], $result['data']),
            'meta' => $result['meta'],
        ]);
    }

    /**
     * 获取出库单详情
     */
    #[Route('/orders/{id}', name: 'warehouse_outbound_detail', methods: ['GET'])]
    public function getOrder(
        #[CurrentUser] User $user,
        string $id
    ): JsonResponse {
        $warehouse = $user->getWarehouse();
        if ($warehouse === null) {
            return $this->json(['error' => 'No warehouse assigned'], Response::HTTP_FORBIDDEN);
        }

        $order = $this->outboundOrderRepository->find($id);

        if ($order === null || $order->getWarehouse()->getId() !== $warehouse->getId()) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'data' => $this->serializeOrderDetail($order),
        ]);
    }

    /**
     * 开始拣货
     */
    #[Route('/orders/{id}/start-picking', name: 'warehouse_outbound_start_picking', methods: ['POST'])]
    public function startPicking(
        #[CurrentUser] User $user,
        string $id
    ): JsonResponse {
        $warehouse = $user->getWarehouse();
        if ($warehouse === null) {
            return $this->json(['error' => 'No warehouse assigned'], Response::HTTP_FORBIDDEN);
        }

        $order = $this->outboundOrderRepository->find($id);

        if ($order === null || $order->getWarehouse()->getId() !== $warehouse->getId()) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$order->isPending()) {
            return $this->json(['error' => 'Order is not pending'], Response::HTTP_BAD_REQUEST);
        }

        $order->startPicking();
        $this->entityManager->flush();

        return $this->json([
            'message' => $this->translator->trans('outbound.order.picking_started'),
            'data' => $this->serializeOrderDetail($order),
        ]);
    }

    /**
     * 完成拣货并开始打包
     */
    #[Route('/orders/{id}/start-packing', name: 'warehouse_outbound_start_packing', methods: ['POST'])]
    public function startPacking(
        #[CurrentUser] User $user,
        string $id
    ): JsonResponse {
        $warehouse = $user->getWarehouse();
        if ($warehouse === null) {
            return $this->json(['error' => 'No warehouse assigned'], Response::HTTP_FORBIDDEN);
        }

        $order = $this->outboundOrderRepository->find($id);

        if ($order === null || $order->getWarehouse()->getId() !== $warehouse->getId()) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$order->isPicking()) {
            return $this->json(['error' => 'Order is not in picking status'], Response::HTTP_BAD_REQUEST);
        }

        $order->completePicking();
        $order->startPacking();
        $this->entityManager->flush();

        return $this->json([
            'message' => $this->translator->trans('outbound.order.packing_started'),
            'data' => $this->serializeOrderDetail($order),
        ]);
    }

    /**
     * 完成打包，准备发货
     */
    #[Route('/orders/{id}/complete-packing', name: 'warehouse_outbound_complete_packing', methods: ['POST'])]
    public function completePacking(
        #[CurrentUser] User $user,
        string $id
    ): JsonResponse {
        $warehouse = $user->getWarehouse();
        if ($warehouse === null) {
            return $this->json(['error' => 'No warehouse assigned'], Response::HTTP_FORBIDDEN);
        }

        $order = $this->outboundOrderRepository->find($id);

        if ($order === null || $order->getWarehouse()->getId() !== $warehouse->getId()) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$order->isPacking()) {
            return $this->json(['error' => 'Order is not in packing status'], Response::HTTP_BAD_REQUEST);
        }

        $order->completePacking();
        $this->entityManager->flush();

        return $this->json([
            'message' => $this->translator->trans('outbound.order.ready_to_ship'),
            'data' => $this->serializeOrderDetail($order),
        ]);
    }

    /**
     * 标记发货
     */
    #[Route('/orders/{id}/ship', name: 'warehouse_outbound_ship', methods: ['POST'])]
    public function shipOrder(
        #[CurrentUser] User $user,
        string $id,
        Request $request
    ): JsonResponse {
        $warehouse = $user->getWarehouse();
        if ($warehouse === null) {
            return $this->json(['error' => 'No warehouse assigned'], Response::HTTP_FORBIDDEN);
        }

        $order = $this->outboundOrderRepository->find($id);

        if ($order === null || $order->getWarehouse()->getId() !== $warehouse->getId()) {
            return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$order->isReady()) {
            return $this->json(['error' => 'Order is not ready to ship'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $carrier = $data['carrier'] ?? '';
        $trackingNumber = $data['trackingNumber'] ?? '';

        if (empty($carrier) || empty($trackingNumber)) {
            return $this->json(['error' => 'Carrier and tracking number are required'], Response::HTTP_BAD_REQUEST);
        }

        $order->markShipped($carrier, $trackingNumber);
        $this->entityManager->flush();

        return $this->json([
            'message' => $this->translator->trans('outbound.order.shipped'),
            'data' => $this->serializeOrderDetail($order),
        ]);
    }

    /**
     * 按仓库分页查询出库单
     */
    private function findByWarehousePaginated($warehouse, int $page, int $limit, ?string $status): array
    {
        $qb = $this->outboundOrderRepository->createQueryBuilder('o')
            ->where('o.warehouse = :warehouse')
            ->setParameter('warehouse', $warehouse)
            ->orderBy('o.createdAt', 'DESC');

        if ($status !== null) {
            $qb->andWhere('o.status = :status')
                ->setParameter('status', $status);
        }

        // 计算总数
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // 分页
        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $data = $qb->getQuery()->getResult();

        return [
            'data' => $data,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => (int) ceil($total / $limit),
            ],
        ];
    }

    /**
     * 序列化出库单（列表）
     */
    private function serializeOrder(OutboundOrder $order): array
    {
        return [
            'id' => $order->getId(),
            'outboundNo' => $order->getOutboundNo(),
            'outboundType' => $order->getOutboundType(),
            'status' => $order->getStatus(),
            'syncStatus' => $order->getSyncStatus(),
            'receiverName' => $order->getReceiverName(),
            'receiverPhone' => $order->getReceiverPhone(),
            'receiverAddress' => $order->getReceiverAddress(),
            'totalQuantity' => $order->getTotalQuantity(),
            'shippingCarrier' => $order->getShippingCarrier(),
            'trackingNumber' => $order->getTrackingNumber(),
            'shippedAt' => $order->getShippedAt()?->format('Y-m-d H:i:s'),
            'createdAt' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * 序列化出库单（详情）
     */
    private function serializeOrderDetail(OutboundOrder $order): array
    {
        $data = $this->serializeOrder($order);

        $data['externalId'] = $order->getExternalId();
        $data['remark'] = $order->getRemark();
        $data['cancelReason'] = $order->getCancelReason();

        // 时间节点
        $data['pickingStartedAt'] = $order->getPickingStartedAt()?->format('Y-m-d H:i:s');
        $data['pickingCompletedAt'] = $order->getPickingCompletedAt()?->format('Y-m-d H:i:s');
        $data['packingStartedAt'] = $order->getPackingStartedAt()?->format('Y-m-d H:i:s');
        $data['packingCompletedAt'] = $order->getPackingCompletedAt()?->format('Y-m-d H:i:s');

        // 明细
        $data['items'] = array_map(function ($item) {
            return [
                'id' => $item->getId(),
                'productSkuId' => $item->getProductSku()?->getId(),
                'skuName' => $item->getSkuName(),
                'productName' => $item->getProductName(),
                'quantity' => $item->getQuantity(),
                'pickedQuantity' => $item->getPickedQuantity(),
            ];
        }, $order->getItems()->toArray());

        return $data;
    }
}
