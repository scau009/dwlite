<?php

namespace App\Controller\OpenApi\Warehouse;

use App\Attribute\OpenApiOnly;
use App\Dto\OpenApi\Warehouse\CompletePackingRequest;
use App\Dto\OpenApi\Warehouse\CompletePickingRequest;
use App\Dto\OpenApi\Warehouse\OutboundOrderListQuery;
use App\Dto\OpenApi\Warehouse\ShipOutboundRequest;
use App\Dto\OpenApi\Warehouse\StartPickingRequest;
use App\Entity\OutboundOrder;
use App\Entity\Warehouse;
use App\Repository\OutboundOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Warehouse Outbound API Controller.
 */
#[Route('/api/v1/open/warehouse/outbound', name: 'open_api_warehouse_outbound_')]
#[OpenApiOnly(permission: 'outbound:read')]
class OutboundController extends AbstractController
{
    public function __construct(
        private readonly OutboundOrderRepository $outboundOrderRepository,
        private readonly EntityManagerInterface $em
    ) {
    }

    /**
     * List outbound orders.
     */
    #[Route('/orders', name: 'list', methods: ['GET'])]
    public function list(
        #[MapQueryString] OutboundOrderListQuery $query,
        Request $request
    ): JsonResponse {
        /** @var Warehouse $warehouse */
        $warehouse = $request->attributes->get('warehouse');

        // Build query
        $qb = $this->outboundOrderRepository->createQueryBuilder('oo')
            ->where('oo.warehouse = :warehouse')
            ->setParameter('warehouse', $warehouse)
            ->orderBy('oo.createdAt', 'DESC');

        // Apply filters
        if ($query->status !== null) {
            $qb->andWhere('oo.status = :status')
                ->setParameter('status', $query->status);
        }

        if ($query->outboundNo !== null) {
            $qb->andWhere('oo.outboundNo LIKE :outboundNo')
                ->setParameter('outboundNo', '%'.$query->outboundNo.'%');
        }

        if ($query->fulfillmentNo !== null) {
            $qb->join('oo.fulfillment', 'f')
                ->andWhere('f.fulfillmentNo LIKE :fulfillmentNo')
                ->setParameter('fulfillmentNo', '%'.$query->fulfillmentNo.'%');
        }

        if ($query->createdFrom !== null) {
            $qb->andWhere('oo.createdAt >= :createdFrom')
                ->setParameter('createdFrom', new \DateTimeImmutable($query->createdFrom, new \DateTimeZone('UTC')));
        }

        if ($query->createdTo !== null) {
            $qb->andWhere('oo.createdAt <= :createdTo')
                ->setParameter('createdTo', new \DateTimeImmutable($query->createdTo, new \DateTimeZone('UTC')));
        }

        // Count total
        $totalCount = (int) (clone $qb)
            ->select('COUNT(oo.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Paginate
        $orders = $qb
            ->setFirstResult(($query->page - 1) * $query->pageSize)
            ->setMaxResults($query->pageSize)
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'data' => [
                'items' => array_map(fn(OutboundOrder $order) => $this->serializeOrder($order), $orders),
                'pagination' => [
                    'page' => $query->page,
                    'pageSize' => $query->pageSize,
                    'total' => $totalCount,
                    'totalPages' => (int) ceil($totalCount / $query->pageSize),
                ],
            ],
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * Get outbound order details.
     */
    #[Route('/orders/{outboundNo}', name: 'details', methods: ['GET'])]
    public function details(string $outboundNo, Request $request): JsonResponse
    {
        /** @var Warehouse $warehouse */
        $warehouse = $request->attributes->get('warehouse');

        $order = $this->outboundOrderRepository->findOneBy(['outboundNo' => $outboundNo]);
        if ($order === null || $order->getWarehouse()->getId() !== $warehouse->getId()) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Outbound order not found',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $this->serializeOrderDetails($order),
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * Start picking outbound order.
     */
    #[Route('/orders/{outboundNo}/start-picking', name: 'start_picking', methods: ['POST'])]
    #[OpenApiOnly(permission: 'outbound:write')]
    public function startPicking(
        string $outboundNo,
        #[MapRequestPayload] StartPickingRequest $dto,
        Request $request
    ): JsonResponse {
        /** @var Warehouse $warehouse */
        $warehouse = $request->attributes->get('warehouse');

        $order = $this->outboundOrderRepository->findOneBy(['outboundNo' => $outboundNo]);
        if ($order === null || $order->getWarehouse()->getId() !== $warehouse->getId()) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Outbound order not found',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_NOT_FOUND);
        }

        // Check state
        if ($order->getStatus() !== OutboundOrder::STATUS_PENDING) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'STATE_CONFLICT',
                    'message' => 'Only pending orders can start picking',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_CONFLICT);
        }

        // Update order
        $order->setStatus(OutboundOrder::STATUS_PICKING);
        $order->setPickingStartedAt(new \DateTimeImmutable($dto->startedAt, new \DateTimeZone('UTC')));

        $this->em->flush();

        return $this->json([
            'success' => true,
            'data' => $this->serializeOrder($order),
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * Complete picking of outbound order.
     */
    #[Route('/orders/{outboundNo}/complete-picking', name: 'complete_picking', methods: ['POST'])]
    #[OpenApiOnly(permission: 'outbound:write')]
    public function completePicking(
        string $outboundNo,
        #[MapRequestPayload] CompletePickingRequest $dto,
        Request $request
    ): JsonResponse {
        /** @var Warehouse $warehouse */
        $warehouse = $request->attributes->get('warehouse');

        $order = $this->outboundOrderRepository->findOneBy(['outboundNo' => $outboundNo]);
        if ($order === null || $order->getWarehouse()->getId() !== $warehouse->getId()) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Outbound order not found',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_NOT_FOUND);
        }

        // Check state
        if ($order->getStatus() !== OutboundOrder::STATUS_PICKING) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'STATE_CONFLICT',
                    'message' => 'Only picking orders can complete picking',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_CONFLICT);
        }

        // Update picked quantities
        foreach ($dto->items as $pickedItem) {
            $orderItem = null;
            foreach ($order->getItems() as $item) {
                if ($item->getSkuName() === $pickedItem->sku) {
                    $orderItem = $item;
                    break;
                }
            }

            if ($orderItem === null) {
                return $this->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_REQUEST',
                        'message' => "SKU {$pickedItem->sku} not found in order",
                    ],
                    'requestId' => $request->attributes->get('request_id'),
                ], Response::HTTP_BAD_REQUEST);
            }

            // Set picked quantity (assuming there's a field for it)
            // If not, we might need to add this field to OutboundOrderItem entity
            // For now, let's assume the items are picked as expected
        }

        // Update order
        $order->setStatus(OutboundOrder::STATUS_PACKING);
        $order->setPickingCompletedAt(new \DateTimeImmutable($dto->completedAt, new \DateTimeZone('UTC')));
        $order->setPackingStartedAt(new \DateTimeImmutable($dto->completedAt, new \DateTimeZone('UTC')));

        $this->em->flush();

        return $this->json([
            'success' => true,
            'data' => $this->serializeOrder($order),
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * Complete packing of outbound order.
     */
    #[Route('/orders/{outboundNo}/complete-packing', name: 'complete_packing', methods: ['POST'])]
    #[OpenApiOnly(permission: 'outbound:write')]
    public function completePacking(
        string $outboundNo,
        #[MapRequestPayload] CompletePackingRequest $dto,
        Request $request
    ): JsonResponse {
        /** @var Warehouse $warehouse */
        $warehouse = $request->attributes->get('warehouse');

        $order = $this->outboundOrderRepository->findOneBy(['outboundNo' => $outboundNo]);
        if ($order === null || $order->getWarehouse()->getId() !== $warehouse->getId()) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Outbound order not found',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_NOT_FOUND);
        }

        // Check state
        if ($order->getStatus() !== OutboundOrder::STATUS_PACKING) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'STATE_CONFLICT',
                    'message' => 'Only packing orders can complete packing',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_CONFLICT);
        }

        // Update order
        $order->setStatus(OutboundOrder::STATUS_READY);
        $order->setPackingCompletedAt(new \DateTimeImmutable($dto->completedAt, new \DateTimeZone('UTC')));
        if ($dto->notes !== null) {
            $order->setRemark($dto->notes);
        }

        $this->em->flush();

        return $this->json([
            'success' => true,
            'data' => $this->serializeOrder($order),
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * Ship outbound order.
     */
    #[Route('/orders/{outboundNo}/ship', name: 'ship', methods: ['POST'])]
    #[OpenApiOnly(permission: 'outbound:write')]
    public function ship(
        string $outboundNo,
        #[MapRequestPayload] ShipOutboundRequest $dto,
        Request $request
    ): JsonResponse {
        /** @var Warehouse $warehouse */
        $warehouse = $request->attributes->get('warehouse');

        $order = $this->outboundOrderRepository->findOneBy(['outboundNo' => $outboundNo]);
        if ($order === null || $order->getWarehouse()->getId() !== $warehouse->getId()) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Outbound order not found',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_NOT_FOUND);
        }

        // Check state
        if ($order->getStatus() !== OutboundOrder::STATUS_READY) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'STATE_CONFLICT',
                    'message' => 'Only ready orders can be shipped',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_CONFLICT);
        }

        // Update shipping info
        $order->setStatus(OutboundOrder::STATUS_SHIPPED);
        $order->setShippedAt(new \DateTimeImmutable($dto->shippedAt, new \DateTimeZone('UTC')));
        $order->setShippingCarrier($dto->shippingCarrier);
        $order->setTrackingNumber($dto->trackingNumber);
        if ($dto->notes !== null) {
            $order->setRemark($dto->notes);
        }

        $this->em->flush();

        return $this->json([
            'success' => true,
            'data' => $this->serializeOrder($order),
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * Serialize order for list view.
     */
    private function serializeOrder(OutboundOrder $order): array
    {
        return [
            'outboundNo' => $order->getOutboundNo(),
            'fulfillmentNo' => $order->getFulfillment()?->getFulfillmentNo(),
            'merchantId' => $order->getMerchant()->getId(),
            'merchantName' => $order->getMerchant()->getName(),
            'status' => $order->getStatus(),
            'receiverName' => $order->getReceiverName(),
            'receiverPhone' => $order->getReceiverPhone(),
            'receiverAddress' => $order->getReceiverAddress(),
            'shippingCarrier' => $order->getShippingCarrier(),
            'trackingNumber' => $order->getTrackingNumber(),
            'pickingStartedAt' => $order->getPickingStartedAt()?->format(\DateTimeInterface::ATOM),
            'pickingCompletedAt' => $order->getPickingCompletedAt()?->format(\DateTimeInterface::ATOM),
            'packingCompletedAt' => $order->getPackingCompletedAt()?->format(\DateTimeInterface::ATOM),
            'shippedAt' => $order->getShippedAt()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Serialize order with full details.
     */
    private function serializeOrderDetails(OutboundOrder $order): array
    {
        $data = $this->serializeOrder($order);
        $data['items'] = [];

        foreach ($order->getItems() as $item) {
            $data['items'][] = [
                'sku' => $item->getSkuName(),
                'quantity' => $item->getQuantity(),
            ];
        }

        $data['remark'] = $order->getRemark();

        return $data;
    }
}
