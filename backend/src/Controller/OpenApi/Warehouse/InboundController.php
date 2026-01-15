<?php

namespace App\Controller\OpenApi\Warehouse;

use App\Attribute\OpenApiOnly;
use App\Dto\OpenApi\Warehouse\ConfirmArrivalRequest;
use App\Dto\OpenApi\Warehouse\InboundOrderListQuery;
use App\Dto\OpenApi\Warehouse\ReceiveInboundRequest;
use App\Dto\OpenApi\Warehouse\ReportExceptionRequest;
use App\Entity\InboundException;
use App\Entity\InboundExceptionItem;
use App\Entity\InboundOrder;
use App\Entity\Warehouse;
use App\Repository\InboundOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Warehouse Inbound API Controller.
 */
#[Route('/api/v1/open/warehouse/inbound', name: 'open_api_warehouse_inbound_')]
#[OpenApiOnly(permission: 'inbound:read')]
class InboundController extends AbstractController
{
    public function __construct(
        private readonly InboundOrderRepository $inboundOrderRepository,
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * List inbound orders.
     */
    #[Route('/orders', name: 'list', methods: ['GET'])]
    public function list(
        #[MapQueryString] InboundOrderListQuery $query,
        Request $request
    ): JsonResponse {
        /** @var Warehouse $warehouse */
        $warehouse = $request->attributes->get('warehouse');

        // Build query
        $qb = $this->inboundOrderRepository->createQueryBuilder('io')
            ->where('io.warehouse = :warehouse')
            ->setParameter('warehouse', $warehouse)
            ->orderBy('io.createdAt', 'DESC');

        // Apply filters
        if ($query->status !== null) {
            $qb->andWhere('io.status = :status')
                ->setParameter('status', $query->status);
        }

        if ($query->orderNo !== null) {
            $qb->andWhere('io.orderNo LIKE :orderNo')
                ->setParameter('orderNo', '%'.$query->orderNo.'%');
        }

        if ($query->merchantCode !== null) {
            $qb->join('io.merchant', 'm')
                ->andWhere('m.code = :merchantCode')
                ->setParameter('merchantCode', $query->merchantCode);
        }

        if ($query->createdFrom !== null) {
            $qb->andWhere('io.createdAt >= :createdFrom')
                ->setParameter('createdFrom', new \DateTimeImmutable($query->createdFrom, new \DateTimeZone('UTC')));
        }

        if ($query->createdTo !== null) {
            $qb->andWhere('io.createdAt <= :createdTo')
                ->setParameter('createdTo', new \DateTimeImmutable($query->createdTo, new \DateTimeZone('UTC')));
        }

        // Count total
        $totalCount = (int) (clone $qb)
            ->select('COUNT(io.id)')
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
                'items' => array_map(fn(InboundOrder $order) => $this->serializeOrder($order), $orders),
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
     * Get inbound order details.
     */
    #[Route('/orders/{orderNo}', name: 'details', methods: ['GET'])]
    public function details(string $orderNo, Request $request): JsonResponse
    {
        /** @var Warehouse $warehouse */
        $warehouse = $request->attributes->get('warehouse');

        $order = $this->inboundOrderRepository->findByOrderNo($orderNo);
        if ($order === null || $order->getWarehouse()->getId() !== $warehouse->getId()) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Inbound order not found',
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
     * Confirm inbound order arrival.
     */
    #[Route('/orders/{orderNo}/confirm-arrival', name: 'confirm_arrival', methods: ['POST'])]
    #[OpenApiOnly(permission: 'inbound:write')]
    public function confirmArrival(
        string $orderNo,
        #[MapRequestPayload] ConfirmArrivalRequest $dto,
        Request $request
    ): JsonResponse {
        /** @var Warehouse $warehouse */
        $warehouse = $request->attributes->get('warehouse');

        $order = $this->inboundOrderRepository->findByOrderNo($orderNo);
        if ($order === null || $order->getWarehouse()->getId() !== $warehouse->getId()) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Inbound order not found',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_NOT_FOUND);
        }

        // Check state
        if ($order->getStatus() !== InboundOrder::STATUS_SHIPPED) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'STATE_CONFLICT',
                    'message' => 'Only shipped orders can be confirmed as arrived',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_CONFLICT);
        }

        // Update order
        $order->setStatus(InboundOrder::STATUS_ARRIVED);
        $order->setArrivedAt(new \DateTimeImmutable($dto->arrivedAt, new \DateTimeZone('UTC')));
        if ($dto->notes !== null) {
            $order->setWarehouseNotes($dto->notes);
        }

        $this->em->flush();

        return $this->json([
            'success' => true,
            'data' => $this->serializeOrder($order),
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * Complete receiving of inbound order.
     */
    #[Route('/orders/{orderNo}/receive', name: 'receive', methods: ['POST'])]
    #[OpenApiOnly(permission: 'inbound:write')]
    public function receive(
        string $orderNo,
        #[MapRequestPayload] ReceiveInboundRequest $dto,
        Request $request
    ): JsonResponse {
        /** @var Warehouse $warehouse */
        $warehouse = $request->attributes->get('warehouse');

        $order = $this->inboundOrderRepository->findByOrderNo($orderNo);
        if ($order === null || $order->getWarehouse()->getId() !== $warehouse->getId()) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Inbound order not found',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_NOT_FOUND);
        }

        // Check state
        if (!in_array($order->getStatus(), [InboundOrder::STATUS_ARRIVED, InboundOrder::STATUS_RECEIVING], true)) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'STATE_CONFLICT',
                    'message' => 'Only arrived or receiving orders can be received',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_CONFLICT);
        }

        // Update order status to receiving if not already
        if ($order->getStatus() === InboundOrder::STATUS_ARRIVED) {
            $order->setStatus(InboundOrder::STATUS_RECEIVING);
        }

        // Update received quantities
        foreach ($dto->items as $receivedItem) {
            $orderItem = null;
            foreach ($order->getItems() as $item) {
                if ($item->getSku() === $receivedItem->sku) {
                    $orderItem = $item;
                    break;
                }
            }

            if ($orderItem === null) {
                return $this->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_REQUEST',
                        'message' => "SKU {$receivedItem->sku} not found in order",
                    ],
                    'requestId' => $request->attributes->get('request_id'),
                ], Response::HTTP_BAD_REQUEST);
            }

            $orderItem->setReceivedQuantity($receivedItem->receivedQuantity);
            if ($receivedItem->shelvingLocation !== null) {
                $orderItem->setShelvingLocation($receivedItem->shelvingLocation);
            }
            if ($receivedItem->notes !== null) {
                $orderItem->setNotes($receivedItem->notes);
            }
        }

        // Recalculate totals
        $order->recalculateTotals();

        // Determine final status
        if ($order->hasQuantityDifference()) {
            $order->setStatus(InboundOrder::STATUS_PARTIAL_COMPLETED);
        } else {
            $order->setStatus(InboundOrder::STATUS_COMPLETED);
        }

        $order->setCompletedAt(new \DateTimeImmutable($dto->receivedAt, new \DateTimeZone('UTC')));
        if ($dto->notes !== null) {
            $order->setWarehouseNotes($dto->notes);
        }

        $this->em->flush();

        return $this->json([
            'success' => true,
            'data' => $this->serializeOrderDetails($order),
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * Report inbound exception.
     */
    #[Route('/orders/{orderNo}/exceptions', name: 'report_exception', methods: ['POST'])]
    #[OpenApiOnly(permission: 'inbound:write')]
    public function reportException(
        string $orderNo,
        #[MapRequestPayload] ReportExceptionRequest $dto,
        Request $request
    ): JsonResponse {
        /** @var Warehouse $warehouse */
        $warehouse = $request->attributes->get('warehouse');

        $order = $this->inboundOrderRepository->findByOrderNo($orderNo);
        if ($order === null || $order->getWarehouse()->getId() !== $warehouse->getId()) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Inbound order not found',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_NOT_FOUND);
        }

        // Map DTO type to entity type
        $exceptionType = match ($dto->type) {
            'damaged' => InboundException::TYPE_DAMAGED,
            'missing' => InboundException::TYPE_QUANTITY_SHORT,
            'wrong_item' => InboundException::TYPE_WRONG_ITEM,
            'quantity_mismatch' => InboundException::TYPE_QUANTITY_SHORT,
            'quality_issue' => InboundException::TYPE_QUALITY_ISSUE,
            'other' => InboundException::TYPE_OTHER,
            default => InboundException::TYPE_OTHER,
        };

        // Create exception
        $exception = InboundException::createForInboundOrder($order, $exceptionType, $dto->description);
        $exception->setEvidenceImages($dto->photoUrls);

        // Add exception item if SKU is specified
        if ($dto->sku !== null && $dto->affectedQuantity !== null) {
            $exceptionItem = new InboundExceptionItem();
            $exceptionItem->setInboundException($exception);
            $exceptionItem->setSku($dto->sku);
            $exceptionItem->setQuantity($dto->affectedQuantity);
            $exception->addItem($exceptionItem);
        }

        $exception->recalculateTotals();

        $this->em->persist($exception);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'data' => [
                'exceptionNo' => $exception->getExceptionNo(),
                'status' => $exception->getStatus(),
                'type' => $exception->getType(),
            ],
            'requestId' => $request->attributes->get('request_id'),
        ], Response::HTTP_CREATED);
    }

    /**
     * Serialize order for list view.
     */
    private function serializeOrder(InboundOrder $order): array
    {
        return [
            'orderNo' => $order->getOrderNo(),
            'merchantCode' => $order->getMerchant()->getCode(),
            'merchantName' => $order->getMerchant()->getName(),
            'status' => $order->getStatus(),
            'totalSkuCount' => $order->getTotalSkuCount(),
            'totalQuantity' => $order->getTotalQuantity(),
            'receivedQuantity' => $order->getReceivedQuantity(),
            'expectedArrivalDate' => $order->getExpectedArrivalDate()?->format(\DateTimeInterface::ATOM),
            'submittedAt' => $order->getSubmittedAt()?->format(\DateTimeInterface::ATOM),
            'shippedAt' => $order->getShippedAt()?->format(\DateTimeInterface::ATOM),
            'arrivedAt' => $order->getArrivedAt()?->format(\DateTimeInterface::ATOM),
            'completedAt' => $order->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            'hasException' => $order->hasException(),
            'createdAt' => $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Serialize order with full details.
     */
    private function serializeOrderDetails(InboundOrder $order): array
    {
        $data = $this->serializeOrder($order);
        $data['items'] = [];

        foreach ($order->getItems() as $item) {
            $data['items'][] = [
                'sku' => $item->getSku(),
                'expectedQuantity' => $item->getExpectedQuantity(),
                'receivedQuantity' => $item->getReceivedQuantity(),
                'shelvingLocation' => $item->getShelvingLocation(),
                'notes' => $item->getNotes(),
            ];
        }

        $data['merchantNotes'] = $order->getMerchantNotes();
        $data['warehouseNotes'] = $order->getWarehouseNotes();

        return $data;
    }
}
