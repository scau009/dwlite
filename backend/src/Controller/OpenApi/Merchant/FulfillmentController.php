<?php

namespace App\Controller\OpenApi\Merchant;

use App\Attribute\OpenApiOnly;
use App\Dto\OpenApi\Merchant\AcceptFulfillmentRequest;
use App\Dto\OpenApi\Merchant\FulfillmentQuery;
use App\Dto\OpenApi\Merchant\RejectFulfillmentRequest;
use App\Entity\Fulfillment;
use App\Entity\Merchant;
use App\Repository\FulfillmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Merchant Fulfillment API Controller.
 */
#[Route('/api/v1/open/merchant/fulfillments', name: 'open_api_merchant_fulfillment_')]
#[OpenApiOnly(permission: 'fulfillment:read')]
class FulfillmentController extends AbstractController
{
    public function __construct(
        private readonly FulfillmentRepository $fulfillmentRepository,
        private readonly EntityManagerInterface $em
    ) {
    }

    /**
     * List fulfillments.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        #[MapQueryString] FulfillmentQuery $query,
        Request $request
    ): JsonResponse {
        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('merchant');

        $qb = $this->fulfillmentRepository->createQueryBuilder('f')
            ->where('f.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->orderBy('f.createdAt', 'DESC');

        if ($query->status !== null) {
            $qb->andWhere('f.status = :status')
                ->setParameter('status', $query->status);
        }

        if ($query->fulfillmentNo !== null) {
            $qb->andWhere('f.fulfillmentNo LIKE :fulfillmentNo')
                ->setParameter('fulfillmentNo', '%'.$query->fulfillmentNo.'%');
        }

        if ($query->createdFrom !== null) {
            $qb->andWhere('f.createdAt >= :createdFrom')
                ->setParameter('createdFrom', new \DateTimeImmutable($query->createdFrom, new \DateTimeZone('UTC')));
        }

        if ($query->createdTo !== null) {
            $qb->andWhere('f.createdAt <= :createdTo')
                ->setParameter('createdTo', new \DateTimeImmutable($query->createdTo, new \DateTimeZone('UTC')));
        }

        $totalCount = (int) (clone $qb)->select('COUNT(f.id)')->getQuery()->getSingleScalarResult();

        $fulfillments = $qb
            ->setFirstResult(($query->page - 1) * $query->pageSize)
            ->setMaxResults($query->pageSize)
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'data' => [
                'items' => array_map(fn (Fulfillment $f) => $this->serializeFulfillment($f), $fulfillments),
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
     * Get fulfillment details.
     */
    #[Route('/{fulfillmentNo}', name: 'details', methods: ['GET'])]
    public function details(string $fulfillmentNo, Request $request): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('merchant');

        $fulfillment = $this->fulfillmentRepository->findOneBy(['fulfillmentNo' => $fulfillmentNo]);
        if ($fulfillment === null || $fulfillment->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Fulfillment not found',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $this->serializeFulfillmentDetails($fulfillment),
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * Accept fulfillment.
     */
    #[Route('/{fulfillmentNo}/accept', name: 'accept', methods: ['POST'])]
    #[OpenApiOnly(permission: 'fulfillment:write')]
    public function accept(
        string $fulfillmentNo,
        #[MapRequestPayload] AcceptFulfillmentRequest $dto,
        Request $request
    ): JsonResponse {
        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('merchant');

        $fulfillment = $this->fulfillmentRepository->findOneBy(['fulfillmentNo' => $fulfillmentNo]);
        if ($fulfillment === null || $fulfillment->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Fulfillment not found',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($fulfillment->getStatus() !== Fulfillment::STATUS_PENDING) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'STATE_CONFLICT',
                    'message' => 'Only pending fulfillments can be accepted',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_CONFLICT);
        }

        $fulfillment->setStatus(Fulfillment::STATUS_PROCESSING);
        $fulfillment->setNotifiedAt(new \DateTimeImmutable($dto->acceptedAt, new \DateTimeZone('UTC')));
        if ($dto->notes !== null) {
            $fulfillment->setRemark($dto->notes);
        }

        $this->em->flush();

        return $this->json([
            'success' => true,
            'data' => $this->serializeFulfillment($fulfillment),
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * Reject fulfillment.
     */
    #[Route('/{fulfillmentNo}/reject', name: 'reject', methods: ['POST'])]
    #[OpenApiOnly(permission: 'fulfillment:write')]
    public function reject(
        string $fulfillmentNo,
        #[MapRequestPayload] RejectFulfillmentRequest $dto,
        Request $request
    ): JsonResponse {
        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('merchant');

        $fulfillment = $this->fulfillmentRepository->findOneBy(['fulfillmentNo' => $fulfillmentNo]);
        if ($fulfillment === null || $fulfillment->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Fulfillment not found',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($fulfillment->getStatus() !== Fulfillment::STATUS_PENDING) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'STATE_CONFLICT',
                    'message' => 'Only pending fulfillments can be rejected',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_CONFLICT);
        }

        $fulfillment->setStatus(Fulfillment::STATUS_REJECTED);
        $fulfillment->setRejectedAt(new \DateTimeImmutable($dto->rejectedAt, new \DateTimeZone('UTC')));
        $fulfillment->setRejectionReason($dto->reason);

        $this->em->flush();

        return $this->json([
            'success' => true,
            'data' => $this->serializeFulfillment($fulfillment),
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    private function serializeFulfillment(Fulfillment $f): array
    {
        return [
            'fulfillmentNo' => $f->getFulfillmentNo(),
            'status' => $f->getStatus(),
            'warehouse' => [
                'code' => $f->getWarehouse()->getCode(),
                'name' => $f->getWarehouse()->getName(),
            ],
            'totalQuantity' => $f->getTotalQuantity(),
            'deadline' => $f->getDeadlineAt()?->format(\DateTimeInterface::ATOM),
            'allocatedAt' => $f->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'acceptedAt' => $f->getNotifiedAt()?->format(\DateTimeInterface::ATOM),
            'rejectedAt' => $f->getRejectedAt()?->format(\DateTimeInterface::ATOM),
            'shippedAt' => $f->getShippedAt()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $f->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function serializeFulfillmentDetails(Fulfillment $f): array
    {
        $data = $this->serializeFulfillment($f);
        $data['items'] = [];

        foreach ($f->getItems() as $item) {
            $unitPrice = $item->getListPrice();
            $totalPrice = $unitPrice !== null ? bcmul($unitPrice, (string) $item->getQuantity(), 2) : null;
            $data['items'][] = [
                'sku' => $item->getOrderItem()->getSkuCode(),
                'quantity' => $item->getQuantity(),
                'unitPrice' => $unitPrice,
                'totalPrice' => $totalPrice,
            ];
        }

        $data['merchantNotes'] = $f->getRemark();
        $data['rejectReason'] = $f->getRejectionReason();

        return $data;
    }
}
