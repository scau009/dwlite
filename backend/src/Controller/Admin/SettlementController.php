<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Attribute\AdminOnly;
use App\Dto\Admin\Query\SettlementListQuery;
use App\Entity\Settlement;
use App\Entity\SettlementItem;
use App\Repository\SettlementItemRepository;
use App\Repository\SettlementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/admin/settlements')]
#[AdminOnly]
class SettlementController extends AbstractController
{
    public function __construct(
        private SettlementRepository $settlementRepository,
        private SettlementItemRepository $settlementItemRepository,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'admin_settlement_list', methods: ['GET'])]
    public function list(#[MapQueryString] SettlementListQuery $query = new SettlementListQuery()): JsonResponse
    {
        $result = $this->settlementRepository->findPaginated(
            $query->getPage(),
            $query->getLimit(),
            $query->toFilters()
        );

        return $this->json([
            'data' => array_map(fn (Settlement $s) => $this->serializeSettlement($s), $result['data']),
            'total' => $result['total'],
            'page' => $query->getPage(),
            'limit' => $query->getLimit(),
        ]);
    }

    #[Route('/{id}', name: 'admin_settlement_detail', methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        $settlement = $this->settlementRepository->find($id);
        if (!$settlement) {
            return $this->json(['error' => $this->translator->trans('admin.settlement.not_found')], Response::HTTP_NOT_FOUND);
        }

        $items = $this->settlementItemRepository->findBySettlement($settlement);

        return $this->json([
            ...$this->serializeSettlement($settlement, true),
            'items' => array_map(fn (SettlementItem $item) => $this->serializeSettlementItem($item), $items),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSettlement(Settlement $settlement, bool $detail = false): array
    {
        $data = [
            'id' => $settlement->getId(),
            'settlementNo' => $settlement->getSettlementNo(),
            'merchantId' => $settlement->getMerchant()->getId(),
            'merchantName' => $settlement->getMerchant()->getName(),
            'orderId' => $settlement->getOrder()->getId(),
            'orderNo' => $settlement->getOrder()->getExternalOrderNo(),
            'fulfillmentId' => $settlement->getFulfillment()->getId(),
            'fulfillmentNo' => $settlement->getFulfillment()->getFulfillmentNo(),
            'grossAmount' => $settlement->getGrossAmount(),
            'commissionRate' => $settlement->getCommissionRate(),
            'commissionAmount' => $settlement->getCommissionAmount(),
            'netAmount' => $settlement->getNetAmount(),
            'currency' => $settlement->getCurrency(),
            'status' => $settlement->getStatus(),
            'settlementDays' => $settlement->getSettlementDays(),
            'scheduledSettleAt' => $settlement->getScheduledSettleAt()->format(\DateTimeInterface::ATOM),
            'settledAt' => $settlement->getSettledAt()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $settlement->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];

        if ($detail) {
            $data['cancelledAt'] = $settlement->getCancelledAt()?->format(\DateTimeInterface::ATOM);
            $data['cancelReason'] = $settlement->getCancelReason();
            $data['walletTransactionId'] = $settlement->getWalletTransactionId();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSettlementItem(SettlementItem $item): array
    {
        return [
            'id' => $item->getId(),
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
}
