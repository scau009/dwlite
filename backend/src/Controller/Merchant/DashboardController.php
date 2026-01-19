<?php

declare(strict_types=1);

namespace App\Controller\Merchant;

use App\Entity\InboundOrder;
use App\Entity\OutboundOrder;
use App\Entity\User;
use App\Repository\InboundExceptionRepository;
use App\Repository\InboundOrderRepository;
use App\Repository\MerchantInventoryRepository;
use App\Repository\MerchantRepository;
use App\Repository\OutboundOrderRepository;
use App\Repository\PayoutRepository;
use App\Repository\SettlementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 商户工作台 - 商户 Dashboard 统计数据.
 */
#[Route('/api/merchant/dashboard')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly MerchantRepository $merchantRepository,
        private readonly MerchantInventoryRepository $inventoryRepository,
        private readonly InboundOrderRepository $inboundRepository,
        private readonly OutboundOrderRepository $outboundRepository,
        private readonly InboundExceptionRepository $exceptionRepository,
        private readonly SettlementRepository $settlementRepository,
        private readonly PayoutRepository $payoutRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * 获取商户工作台统计数据.
     */
    #[Route('', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        // 库存统计
        $inventorySummary = $this->inventoryRepository->getMerchantSummary($merchant);

        // 入库单状态统计
        $inboundCounts = $this->inboundRepository->countByMerchantGroupByStatus($merchant);

        // 出库单状态统计
        $outboundCounts = $this->outboundRepository->countByMerchantGroupByStatus($merchant);

        // 待处理异常
        $pendingExceptions = $this->exceptionRepository->countPendingByMerchant($merchant);

        // 钱包信息
        $depositWallet = $merchant->getDepositWallet();
        $balanceWallet = $merchant->getBalanceWallet();

        // 结算统计
        $settlementSums = $this->settlementRepository->sumByMerchant($merchant);

        // 提现统计
        $payoutSums = $this->payoutRepository->sumByMerchant($merchant);

        return $this->json([
            'summary' => [
                'totalAvailable' => (int) ($inventorySummary['totalAvailable'] ?? 0),
                'totalInTransit' => (int) ($inventorySummary['totalInTransit'] ?? 0),
                'totalReserved' => (int) ($inventorySummary['totalReserved'] ?? 0),
                'totalDamaged' => (int) ($inventorySummary['totalDamaged'] ?? 0),
                'totalSkuCount' => (int) ($inventorySummary['totalSkuCount'] ?? 0),
                'warehouseCount' => (int) ($inventorySummary['warehouseCount'] ?? 0),
                'pendingInbounds' => ($inboundCounts[InboundOrder::STATUS_DRAFT] ?? 0)
                    + ($inboundCounts[InboundOrder::STATUS_PENDING] ?? 0)
                    + ($inboundCounts[InboundOrder::STATUS_SHIPPED] ?? 0),
                'pendingOutbounds' => ($outboundCounts[OutboundOrder::STATUS_DRAFT] ?? 0)
                    + ($outboundCounts[OutboundOrder::STATUS_PENDING] ?? 0),
                'pendingExceptions' => $pendingExceptions,
            ],
            'inbound' => $inboundCounts,
            'outbound' => $outboundCounts,
            'wallet' => [
                'deposit' => [
                    'balance' => $depositWallet?->getBalance() ?? '0.00',
                    'frozenAmount' => $depositWallet?->getFrozenAmount() ?? '0.00',
                ],
                'balance' => [
                    'balance' => $balanceWallet?->getBalance() ?? '0.00',
                    'frozenAmount' => $balanceWallet?->getFrozenAmount() ?? '0.00',
                ],
            ],
            'settlement' => [
                'pendingAmount' => $settlementSums['pending'],
                'settledAmount' => $settlementSums['settled'],
            ],
            'payout' => [
                'availableBalance' => $balanceWallet?->getAvailableBalance() ?? '0.00',
                'processingAmount' => $payoutSums['processing'],
            ],
        ]);
    }

    /**
     * 获取近7天趋势数据.
     */
    #[Route('/trend', methods: ['GET'])]
    public function trend(#[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        // 获取近7天的入库完成数和出库发货数
        $inboundCounts = $this->inboundRepository->countCompletedByMerchantGroupByDate($merchant, 7);
        $outboundCounts = $this->outboundRepository->countShippedByMerchantGroupByDate($merchant, 7);

        // 构建完整的7天数据
        $trend = [];
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Shanghai'));
        for ($i = 6; $i >= 0; --$i) {
            $date = $today->modify("-{$i} days")->format('Y-m-d');
            $trend[] = [
                'date' => $date,
                'inboundCount' => $inboundCounts[$date] ?? 0,
                'outboundCount' => $outboundCounts[$date] ?? 0,
            ];
        }

        return $this->json(['data' => $trend]);
    }

    /**
     * 获取最近的入库单和出库单.
     */
    #[Route('/recent', methods: ['GET'])]
    public function recent(#[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->merchantRepository->findOneBy(['user' => $user]);
        if (!$merchant) {
            return $this->json(['error' => $this->translator->trans('merchant.not_found')], Response::HTTP_NOT_FOUND);
        }

        // 最近5个入库单
        $recentInbounds = $this->inboundRepository->findByMerchant($merchant, null, null, 5);

        // 最近5个出库单
        $recentOutbounds = $this->outboundRepository->findByMerchantPaginated($merchant, null, null, null, null, null, 1, 5);

        // 最近5个待处理异常
        $pendingExceptions = $this->exceptionRepository->findPending($merchant);
        $recentExceptions = array_slice($pendingExceptions, 0, 5);

        return $this->json([
            'data' => [
                'recentInbounds' => array_map(fn (InboundOrder $o) => [
                    'id' => $o->getId(),
                    'orderNo' => $o->getOrderNo(),
                    'status' => $o->getStatus(),
                    'totalQuantity' => $o->getTotalQuantity(),
                    'createdAt' => $o->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ], $recentInbounds),
                'recentOutbounds' => array_map(fn (OutboundOrder $o) => [
                    'id' => $o->getId(),
                    'outboundNo' => $o->getOutboundNo(),
                    'status' => $o->getStatus(),
                    'totalQuantity' => $o->getTotalQuantity(),
                    'createdAt' => $o->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ], $recentOutbounds['items']),
                'recentExceptions' => array_map(fn ($e) => [
                    'id' => $e->getId(),
                    'exceptionNo' => $e->getExceptionNo(),
                    'type' => $e->getType(),
                    'status' => $e->getStatus(),
                    'createdAt' => $e->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ], $recentExceptions),
            ],
        ]);
    }
}
