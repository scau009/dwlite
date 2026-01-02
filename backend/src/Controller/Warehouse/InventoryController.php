<?php

namespace App\Controller\Warehouse;

use App\Attribute\WarehouseOnly;
use App\Entity\Warehouse;
use App\Repository\MerchantInventoryRepository;
use App\Service\CosService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * 仓库用户 - 库存查询
 */
#[Route('/api/warehouse/inventory')]
#[IsGranted('ROLE_USER')]
#[WarehouseOnly]
class InventoryController extends AbstractController
{
    public function __construct(
        private MerchantInventoryRepository $inventoryRepository,
        private CosService $cosService,
    ) {
    }

    /**
     * 获取本仓库库存列表
     */
    #[Route('', name: 'warehouse_inventory_list', methods: ['GET'])]
    public function listInventory(
        Warehouse $warehouse,
        Request $request
    ): JsonResponse {
        $page = max(1, (int) $request->query->get('page', '1'));
        $limit = min(50, max(1, (int) $request->query->get('limit', '20')));
        $search = $request->query->get('search');
        $hasStock = $request->query->getBoolean('hasStock', false);

        $filters = [];
        if ($search) {
            $filters['search'] = $search;
        }
        if ($hasStock) {
            $filters['hasStock'] = true;
        }

        $result = $this->inventoryRepository->findByWarehousePaginated(
            $warehouse,
            $page,
            $limit,
            $filters
        );

        return $this->json([
            'data' => array_map([$this, 'serializeInventory'], $result['data']),
            'meta' => $result['meta'],
        ]);
    }

    /**
     * 获取本仓库库存汇总
     */
    #[Route('/summary', name: 'warehouse_inventory_summary', methods: ['GET'])]
    public function getSummary(
        Warehouse $warehouse
    ): JsonResponse {
        $summary = $this->inventoryRepository->getWarehouseSummary($warehouse);

        return $this->json([
            'data' => [
                'warehouse' => [
                    'id' => $warehouse->getId(),
                    'name' => $warehouse->getName(),
                    'code' => $warehouse->getCode(),
                ],
                'totalInTransit' => (int) ($summary['totalInTransit'] ?? 0),
                'totalAvailable' => (int) ($summary['totalAvailable'] ?? 0),
                'totalReserved' => (int) ($summary['totalReserved'] ?? 0),
                'totalDamaged' => (int) ($summary['totalDamaged'] ?? 0),
                'totalSkuCount' => (int) ($summary['totalSkuCount'] ?? 0),
            ],
        ]);
    }

    /**
     * 序列化库存
     */
    private function serializeInventory($inventory): array
    {
        $sku = $inventory->getProductSku();
        $product = $sku?->getProduct();

        $primaryImageUrl = null;
        if ($product) {
            $primaryImage = $product->getPrimaryImage();
            if ($primaryImage) {
                $primaryImageUrl = $this->cosService->getSignedUrl(
                    $primaryImage->getCosKey(),
                    3600,
                    'imageMogr2/thumbnail/80x80>'
                );
            }
        }

        return [
            'id' => $inventory->getId(),
            'merchant' => [
                'id' => $inventory->getMerchant()->getId(),
                'name' => $inventory->getMerchant()->getName(),
            ],
            'product' => $product ? [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'styleNumber' => $product->getStyleNumber(),
                'color' => $product->getColor(),
                'primaryImage' => $primaryImageUrl,
            ] : null,
            'sku' => $sku ? [
                'id' => $sku->getId(),
                'skuName' => $sku->getSkuName(),
                'sizeUnit' => $sku->getSizeUnit()?->value,
                'sizeValue' => $sku->getSizeValue(),
            ] : null,
            'quantityInTransit' => $inventory->getQuantityInTransit(),
            'quantityAvailable' => $inventory->getQuantityAvailable(),
            'quantityReserved' => $inventory->getQuantityReserved(),
            'quantityDamaged' => $inventory->getQuantityDamaged(),
            'quantityAllocated' => $inventory->getQuantityAllocated(),
            'averageCost' => $inventory->getAverageCost(),
            'safetyStock' => $inventory->getSafetyStock(),
            'updatedAt' => $inventory->getUpdatedAt()->format('c'),
        ];
    }
}
