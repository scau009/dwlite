<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Merchant\Query\MerchantSettlementListQuery;
use App\Entity\Settlement;
use App\Entity\SettlementItem;
use App\Entity\User;
use App\Repository\MerchantRepository;
use App\Repository\SettlementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 商户结算单查询.
 */
#[Route('/api/merchant/settlements')]
class MerchantSettlementController extends AbstractController
{
    public function __construct(
        private readonly MerchantRepository $merchantRepository,
        private readonly SettlementRepository $settlementRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * 获取我的结算单列表.
     */
    #[Route('', name: 'merchant_settlements_list', methods: ['GET'])]
    public function list(
        #[CurrentUser] User $user,
        #[MapQueryString] MerchantSettlementListQuery $query = new MerchantSettlementListQuery()
    ): JsonResponse {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $result = $this->settlementRepository->findByMerchantPaginated(
            $merchant,
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

    /**
     * 获取结算单详情.
     */
    #[Route('/{id}', name: 'merchant_settlement_detail', methods: ['GET'])]
    public function detail(string $id, #[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $settlement = $this->settlementRepository->find($id);
        if (!$settlement || $settlement->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => $this->translator->trans('settlement.not_found')], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeSettlementDetail($settlement));
    }

    /**
     * 获取结算统计摘要.
     */
    #[Route('/summary', name: 'merchant_settlements_summary', methods: ['GET'], priority: 10)]
    public function summary(#[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        $sums = $this->settlementRepository->sumByMerchant($merchant);

        return $this->json([
            'pendingAmount' => $sums['pending'],
            'settledAmount' => $sums['settled'],
        ]);
    }

    private function serializeSettlement(Settlement $settlement): array
    {
        $order = $settlement->getOrder();
        $fulfillment = $settlement->getFulfillment();

        return [
            'id' => $settlement->getId(),
            'settlementNo' => $settlement->getSettlementNo(),
            'orderNo' => $order->getOrderNo(),
            'fulfillmentNo' => $fulfillment->getFulfillmentNo(),
            'grossAmount' => $settlement->getGrossAmount(),
            'commissionRate' => $settlement->getCommissionRate(),
            'commissionAmount' => $settlement->getCommissionAmount(),
            'netAmount' => $settlement->getNetAmount(),
            'currency' => $settlement->getCurrency(),
            'status' => $settlement->getStatus(),
            'settlementDays' => $settlement->getSettlementDays(),
            'scheduledSettleAt' => $settlement->getScheduledSettleAt()->format('c'),
            'settledAt' => $settlement->getSettledAt()?->format('c'),
            'createdAt' => $settlement->getCreatedAt()->format('c'),
        ];
    }

    private function serializeSettlementDetail(Settlement $settlement): array
    {
        $data = $this->serializeSettlement($settlement);

        // 添加明细
        $data['items'] = array_map(fn (SettlementItem $item) => [
            'id' => $item->getId(),
            'skuCode' => $item->getSkuCode(),
            'productName' => $item->getProductName(),
            'quantity' => $item->getQuantity(),
            'unitPrice' => $item->getUnitPrice(),
            'grossAmount' => $item->getGrossAmount(),
            'commissionRate' => $item->getCommissionRate(),
            'commissionAmount' => $item->getCommissionAmount(),
            'netAmount' => $item->getNetAmount(),
        ], $settlement->getItems()->toArray());

        // 添加时间信息
        $data['cancelledAt'] = $settlement->getCancelledAt()?->format('c');
        $data['cancelReason'] = $settlement->getCancelReason();

        return $data;
    }
}
