<?php

namespace App\Controller;

use App\Entity\MerchantInventory;
use App\Entity\User;
use App\Repository\MerchantInventoryRepository;
use App\Repository\MerchantRepository;
use App\Repository\WarehouseRepository;
use App\Service\CosService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/merchant/inventory')]
#[IsGranted('ROLE_USER')]
class MerchantInventoryController extends AbstractController
{
    public function __construct(
        private MerchantInventoryRepository $inventoryRepository,
        private MerchantRepository $merchantRepository,
        private WarehouseRepository $warehouseRepository,
        private CosService $cosService,
    ) {
    }

    /**
     * 获取当前商户
     */
    private function getCurrentMerchant(User $user)
    {
        $merchant = $this->merchantRepository->findByUser($user);
        if ($merchant === null) {
            throw $this->createAccessDeniedException('Merchant not found');
        }
        return $merchant;
    }

    /**
     * 获取商户库存列表（分页）
     */
    #[Route('', name: 'merchant_inventory_list', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->getCurrentMerchant($user);

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        $filters = [];

        if ($request->query->has('search')) {
            $filters['search'] = $request->query->get('search');
        }

        if ($request->query->has('warehouseId')) {
            $filters['warehouseId'] = $request->query->get('warehouseId');
        }

        if ($request->query->has('stockStatus')) {
            $filters['stockStatus'] = $request->query->get('stockStatus');
        }

        if ($request->query->get('hasStock') === 'true') {
            $filters['hasStock'] = true;
        }

        $result = $this->inventoryRepository->findByMerchantPaginated($merchant, $page, $limit, $filters);

        return $this->json([
            'data' => array_map(fn(MerchantInventory $inv) => $this->formatInventory($inv), $result['data']),
            'meta' => $result['meta'],
        ]);
    }

    /**
     * 获取商户库存汇总
     */
    #[Route('/summary', name: 'merchant_inventory_summary', methods: ['GET'])]
    public function summary(#[CurrentUser] User $user): JsonResponse
    {
        $merchant = $this->getCurrentMerchant($user);

        $summary = $this->inventoryRepository->getMerchantSummary($merchant);

        return $this->json([
            'data' => [
                'totalInTransit' => (int) ($summary['totalInTransit'] ?? 0),
                'totalAvailable' => (int) ($summary['totalAvailable'] ?? 0),
                'totalReserved' => (int) ($summary['totalReserved'] ?? 0),
                'totalDamaged' => (int) ($summary['totalDamaged'] ?? 0),
                'totalSkuCount' => (int) ($summary['totalSkuCount'] ?? 0),
                'warehouseCount' => (int) ($summary['warehouseCount'] ?? 0),
            ],
        ]);
    }

    /**
     * 获取可用仓库列表（用于筛选）
     */
    #[Route('/warehouses', name: 'merchant_inventory_warehouses', methods: ['GET'])]
    public function warehouses(): JsonResponse
    {
        // 获取所有活跃的平台仓库
        $warehouses = $this->warehouseRepository->findActivePlatformWarehouses();

        return $this->json([
            'data' => array_map(fn($w) => [
                'id' => $w->getId(),
                'code' => $w->getCode(),
                'name' => $w->getName(),
            ], $warehouses),
        ]);
    }

    /**
     * 格式化库存记录
     */
    private function formatInventory(MerchantInventory $inventory): array
    {
        $sku = $inventory->getProductSku();
        $product = $sku->getProduct();
        $warehouse = $inventory->getWarehouse();
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
            'warehouse' => [
                'id' => $warehouse->getId(),
                'code' => $warehouse->getCode(),
                'name' => $warehouse->getName(),
            ],
            'product' => $product ? [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'styleNumber' => $product->getStyleNumber(),
                'color' => $product->getColor(),
                'primaryImage' => $primaryImageUrl,
            ] : null,
            'sku' => [
                'id' => $sku->getId(),
                'skuName' => $sku->getSkuName(),
                'sizeUnit' => $sku->getSizeUnit(),
                'sizeValue' => $sku->getSizeValue(),
            ],
            'quantityInTransit' => $inventory->getQuantityInTransit(),
            'quantityAvailable' => $inventory->getQuantityAvailable(),
            'quantityReserved' => $inventory->getQuantityReserved(),
            'quantityDamaged' => $inventory->getQuantityDamaged(),
            'quantityAllocated' => $inventory->getQuantityAllocated(),
            'averageCost' => $inventory->getAverageCost(),
            'safetyStock' => $inventory->getSafetyStock(),
            'isBelowSafetyStock' => $inventory->isBelowSafetyStock(),
            'lastInboundAt' => $inventory->getLastInboundAt()?->format('c'),
            'lastOutboundAt' => $inventory->getLastOutboundAt()?->format('c'),
            'updatedAt' => $inventory->getUpdatedAt()->format('c'),
        ];
    }
}
