<?php

namespace App\Controller\Warehouse;

use App\Attribute\WarehouseOnly;
use App\Entity\Warehouse;
use App\Repository\InboundOrderRepository;
use App\Repository\OutboundOrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * 仓库用户 - 工作台.
 */
#[Route('/api/warehouse/dashboard')]
#[IsGranted('ROLE_USER')]
#[WarehouseOnly]
class DashboardController extends AbstractController
{
    public function __construct(
        private InboundOrderRepository $inboundOrderRepository,
        private OutboundOrderRepository $outboundOrderRepository,
    ) {
    }

    /**
     * 获取近7天的入库/出库趋势数据.
     */
    #[Route('/trend', name: 'warehouse_dashboard_trend', methods: ['GET'])]
    public function getTrend(Warehouse $warehouse): JsonResponse
    {
        $inboundCounts = $this->inboundOrderRepository->countCompletedByWarehouseGroupByDate($warehouse, 7);
        $outboundCounts = $this->outboundOrderRepository->countShippedByWarehouseGroupByDate($warehouse, 7);

        // 生成近7天的日期列表
        $trend = [];
        $timezone = new \DateTimeZone('Asia/Shanghai');
        for ($i = 6; $i >= 0; --$i) {
            $date = (new \DateTimeImmutable("-{$i} days", $timezone))->format('Y-m-d');
            $trend[] = [
                'date' => $date,
                'inboundCount' => $inboundCounts[$date] ?? 0,
                'outboundCount' => $outboundCounts[$date] ?? 0,
            ];
        }

        return $this->json([
            'data' => $trend,
        ]);
    }
}
