<?php

namespace App\Controller\OpenApi\Merchant;

use App\Attribute\OpenApiOnly;
use App\Dto\OpenApi\Merchant\SettlementQuery;
use App\Entity\Merchant;
use App\Entity\Settlement;
use App\Repository\SettlementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Merchant Settlement API Controller.
 */
#[Route('/api/v1/open/merchant/settlements', name: 'open_api_merchant_settlement_')]
#[OpenApiOnly(permission: 'settlement:read')]
class SettlementController extends AbstractController
{
    public function __construct(
        private readonly SettlementRepository $settlementRepository
    ) {
    }

    /**
     * List settlements.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        #[MapQueryString] SettlementQuery $query,
        Request $request
    ): JsonResponse {
        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('merchant');

        $filters = [];
        if ($query->status !== null) {
            $filters['status'] = $query->status;
        }
        if ($query->settlementNo !== null) {
            $filters['settlementNo'] = $query->settlementNo;
        }
        if ($query->scheduledSettleAtFrom !== null) {
            $filters['scheduledSettleAtFrom'] = new \DateTimeImmutable($query->scheduledSettleAtFrom, new \DateTimeZone('UTC'));
        }
        if ($query->scheduledSettleAtTo !== null) {
            $filters['scheduledSettleAtTo'] = new \DateTimeImmutable($query->scheduledSettleAtTo, new \DateTimeZone('UTC'));
        }

        $result = $this->settlementRepository->findByMerchantPaginated(
            $merchant,
            $query->page,
            $query->pageSize,
            $filters
        );

        return $this->json([
            'success' => true,
            'data' => [
                'items' => array_map(fn(Settlement $s) => $this->serializeSettlement($s), $result['data']),
                'pagination' => [
                    'page' => $query->page,
                    'pageSize' => $query->pageSize,
                    'total' => $result['total'],
                    'totalPages' => (int) ceil($result['total'] / $query->pageSize),
                ],
            ],
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * Get settlement details.
     */
    #[Route('/{settlementNo}', name: 'details', methods: ['GET'])]
    public function details(string $settlementNo, Request $request): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('merchant');

        $settlement = $this->settlementRepository->findOneBy(['settlementNo' => $settlementNo]);
        if ($settlement === null || $settlement->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Settlement not found',
                ],
                'requestId' => $request->attributes->get('request_id'),
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $this->serializeSettlementDetails($settlement),
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * Get settlement summary.
     */
    #[Route('/summary', name: 'summary', methods: ['GET'], priority: 1)]
    public function summary(Request $request): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('merchant');

        $sums = $this->settlementRepository->sumByMerchant($merchant);
        $counts = $this->settlementRepository->countByMerchantGroupByStatus($merchant);

        return $this->json([
            'success' => true,
            'data' => [
                'amounts' => [
                    'pending' => $sums['pending'],
                    'settled' => $sums['settled'],
                    'total' => bcadd($sums['pending'], $sums['settled'], 2),
                ],
                'counts' => [
                    'pending' => $counts[Settlement::STATUS_PENDING] ?? 0,
                    'settled' => $counts[Settlement::STATUS_SETTLED] ?? 0,
                    'cancelled' => $counts[Settlement::STATUS_CANCELLED] ?? 0,
                ],
            ],
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    private function serializeSettlement(Settlement $s): array
    {
        return [
            'settlementNo' => $s->getSettlementNo(),
            'status' => $s->getStatus(),
            'fulfillmentNo' => $s->getFulfillment()->getFulfillmentNo(),
            'orderNo' => $s->getOrder()->getOrderNo(),
            'grossAmount' => $s->getGrossAmount(),
            'commissionRate' => $s->getCommissionRate(),
            'commissionAmount' => $s->getCommissionAmount(),
            'netAmount' => $s->getNetAmount(),
            'currency' => $s->getCurrency(),
            'settlementDays' => $s->getSettlementDays(),
            'scheduledSettleAt' => $s->getScheduledSettleAt()->format(\DateTimeInterface::ATOM),
            'settledAt' => $s->getSettledAt()?->format(\DateTimeInterface::ATOM),
            'cancelledAt' => $s->getCancelledAt()?->format(\DateTimeInterface::ATOM),
            'cancelReason' => $s->getCancelReason(),
            'createdAt' => $s->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function serializeSettlementDetails(Settlement $s): array
    {
        $data = $this->serializeSettlement($s);
        $data['items'] = [];

        foreach ($s->getItems() as $item) {
            $data['items'][] = [
                'skuCode' => $item->getSkuCode(),
                'productName' => $item->getProductName(),
                'quantity' => $item->getQuantity(),
                'unitPrice' => $item->getUnitPrice(),
                'grossAmount' => $item->getGrossAmount(),
                'commissionRate' => $item->getCommissionRate(),
                'commissionAmount' => $item->getCommissionAmount(),
                'netAmount' => $item->getNetAmount(),
            ];
        }

        return $data;
    }
}
