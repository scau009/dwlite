<?php

namespace App\Controller\OpenApi\Merchant;

use App\Attribute\OpenApiOnly;
use App\Dto\OpenApi\Merchant\InboundOrderQuery;
use App\Entity\InboundOrder;
use App\Entity\Merchant;
use App\Repository\InboundOrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Merchant Inbound API Controller.
 */
#[Route('/api/v1/open/merchant/inbound', name: 'open_api_merchant_inbound_')]
#[OpenApiOnly(permission: 'merchant_inbound:read')]
class InboundController extends AbstractController
{
    public function __construct(
        private readonly InboundOrderRepository $inboundOrderRepository
    ) {
    }

    /**
     * List inbound orders.
     */
    #[Route('/orders', name: 'list', methods: ['GET'])]
    public function list(
        #[MapQueryString] InboundOrderQuery $query,
        Request $request
    ): JsonResponse {
        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('merchant');

        $qb = $this->inboundOrderRepository->createQueryBuilder('io')
            ->where('io.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->orderBy('io.createdAt', 'DESC');

        if ($query->status !== null) {
            $qb->andWhere('io.status = :status')
                ->setParameter('status', $query->status);
        }

        if ($query->orderNo !== null) {
            $qb->andWhere('io.orderNo LIKE :orderNo')
                ->setParameter('orderNo', '%'.$query->orderNo.'%');
        }

        if ($query->warehouseCode !== null) {
            $qb->join('io.warehouse', 'w')
                ->andWhere('w.code = :warehouseCode')
                ->setParameter('warehouseCode', $query->warehouseCode);
        }

        if ($query->createdFrom !== null) {
            $qb->andWhere('io.createdAt >= :createdFrom')
                ->setParameter('createdFrom', new \DateTimeImmutable($query->createdFrom, new \DateTimeZone('UTC')));
        }

        if ($query->createdTo !== null) {
            $qb->andWhere('io.createdAt <= :createdTo')
                ->setParameter('createdTo', new \DateTimeImmutable($query->createdTo, new \DateTimeZone('UTC')));
        }

        $totalCount = (int) (clone $qb)->select('COUNT(io.id)')->getQuery()->getSingleScalarResult();

        $orders = $qb
            ->setFirstResult(($query->page - 1) * $query->pageSize)
            ->setMaxResults($query->pageSize)
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'data' => [
                'items' => array_map(fn(InboundOrder $order) => [
                    'orderNo' => $order->getOrderNo(),
                    'warehouseCode' => $order->getWarehouse()->getCode(),
                    'warehouseName' => $order->getWarehouse()->getName(),
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
                ], $orders),
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
        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('merchant');

        $order = $this->inboundOrderRepository->findByOrderNo($orderNo);
        if ($order === null || $order->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Inbound order not found',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_NOT_FOUND);
        }

        $data = [
            'orderNo' => $order->getOrderNo(),
            'warehouseCode' => $order->getWarehouse()->getCode(),
            'warehouseName' => $order->getWarehouse()->getName(),
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
            'items' => [],
            'merchantNotes' => $order->getMerchantNotes(),
            'warehouseNotes' => $order->getWarehouseNotes(),
            'createdAt' => $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];

        foreach ($order->getItems() as $item) {
            $data['items'][] = [
                'sku' => $item->getSkuName(),
                'expectedQuantity' => $item->getExpectedQuantity(),
                'receivedQuantity' => $item->getReceivedQuantity(),
            ];
        }

        return $this->json([
            'success' => true,
            'data' => $data,
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }
}
