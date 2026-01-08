<?php

namespace App\Controller;

use App\Dto\Merchant\AdjustInventoryRequest;
use App\Dto\Merchant\CreateInventoryRequest;
use App\Dto\Merchant\ImportInventoryConfirmRequest;
use App\Dto\Merchant\ImportInventoryItemDto;
use App\Entity\MerchantInventory;
use App\Entity\User;
use App\Repository\MerchantInventoryRepository;
use App\Repository\MerchantRepository;
use App\Repository\ProductSkuRepository;
use App\Repository\WarehouseRepository;
use App\Service\CosService;
use App\Service\InventoryService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/merchant/inventory')]
#[IsGranted('ROLE_USER')]
class MerchantInventoryController extends AbstractController
{
    public function __construct(
        private readonly CosService $cosService,
        private readonly InventoryService $inventoryService,
    ) {
    }

    /**
     * 获取商户库存列表（分页，按 styleNumber + sizeValue 分组）.
     */
    #[Route('', name: 'merchant_inventory_list', methods: ['GET'])]
    public function list(
        Request $request,
        #[CurrentUser] User $user,
        MerchantRepository $merchantRepository,
        MerchantInventoryRepository $inventoryRepository,
    ): JsonResponse {
        $merchant = $merchantRepository->findByUser($user);
        if (!$merchant) {
            throw $this->createAccessDeniedException('Merchant not found');
        }

        $page = max(1, (int) $request->query->get('page', '1'));
        $limit = min(100, max(1, (int) $request->query->get('limit', '20')));

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

        $result = $inventoryRepository->findByMerchantPaginated($merchant, $page, $limit, $filters);

        return $this->json([
            'data' => array_map(fn ($inventory) => $this->formatInventory($inventory), $result['data']),
            'meta' => $result['meta'],
        ]);
    }

    /**
     * 获取商户库存汇总.
     */
    #[Route('/summary', name: 'merchant_inventory_summary', methods: ['GET'])]
    public function summary(
        #[CurrentUser] User $user,
        MerchantRepository $merchantRepository,
        MerchantInventoryRepository $inventoryRepository,
    ): JsonResponse {
        $merchant = $merchantRepository->findByUser($user);
        if (!$merchant) {
            throw $this->createAccessDeniedException('Merchant not found');
        }

        $summary = $inventoryRepository->getMerchantSummary($merchant);

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
     * 获取可用仓库列表（用于筛选）.
     */
    #[Route('/warehouses', name: 'merchant_inventory_warehouses', methods: ['GET'])]
    public function warehouses(
        WarehouseRepository $warehouseRepository,
    ): JsonResponse {
        // 获取所有活跃的平台仓库
        $warehouses = $warehouseRepository->findActivePlatformWarehouses();

        return $this->json([
            'data' => array_map(fn ($w) => [
                'id' => $w->getId(),
                'code' => $w->getCode(),
                'name' => $w->getName(),
            ], $warehouses),
        ]);
    }

    /**
     * 格式化分组后的库存记录.
     */
    private function formatGroupedInventory(array $row, array $productImages): array
    {
        $productId = $row['productId'] ?? null;
        $sizeUnit = $row['sizeUnit'] ?? null;
        $sizeValue = $row['sizeValue'] ?? null;

        // 构建 SKU 名称
        $skuName = null;
        if ($sizeUnit && $sizeValue) {
            $unitValue = $sizeUnit->value ?? $sizeUnit;
            $skuName = $unitValue.' '.$sizeValue;
        } elseif ($sizeValue) {
            $skuName = $sizeValue;
        }

        return [
            'id' => $row['styleNumber'].'-'.($sizeValue ?? ''),
            'product' => [
                'id' => $productId,
                'name' => $row['productName'] ?? null,
                'styleNumber' => $row['styleNumber'] ?? null,
                'color' => $row['colorName'] ?? null,
                'primaryImage' => $productId ? ($productImages[$productId] ?? null) : null,
            ],
            'sku' => [
                'id' => $row['skuId'] ?? null,
                'skuName' => $skuName,
                'sizeUnit' => $sizeUnit?->value ?? $sizeUnit,
                'sizeValue' => $sizeValue,
            ],
            'quantityInTransit' => (int) ($row['quantityInTransit'] ?? 0),
            'quantityAvailable' => (int) ($row['quantityAvailable'] ?? 0),
            'quantityReserved' => (int) ($row['quantityReserved'] ?? 0),
            'quantityDamaged' => (int) ($row['quantityDamaged'] ?? 0),
            'quantityAllocated' => (int) ($row['quantityAllocated'] ?? 0),
            'updatedAt' => $row['updatedAt'] instanceof \DateTimeInterface
                ? $row['updatedAt']->format('c')
                : $row['updatedAt'],
        ];
    }

    /**
     * 格式化库存记录.
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
                'barcode' => $sku->getBarcode(),
            ],
            'quantityInTransit' => $inventory->getQuantityInTransit(),
            'quantityAvailable' => $inventory->getQuantityAvailable(),
            'quantityReserved' => $inventory->getQuantityReserved(),
            'quantityDamaged' => $inventory->getQuantityDamaged(),
            'quantityAllocated' => $inventory->getQuantityAllocated(),
            'averageCost' => $inventory->getAverageCost(),
            'currency' => $inventory->getCurrency(),
            'safetyStock' => $inventory->getSafetyStock(),
            'isBelowSafetyStock' => $inventory->isBelowSafetyStock(),
            'lastInboundAt' => $inventory->getLastInboundAt()?->format('c'),
            'lastOutboundAt' => $inventory->getLastOutboundAt()?->format('c'),
            'updatedAt' => $inventory->getUpdatedAt()->format('c'),
        ];
    }

    /**
     * 创建库存（逻辑仓库）.
     */
    #[Route('', name: 'merchant_inventory_create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateInventoryRequest $request,
        #[CurrentUser] User $user,
        MerchantRepository $merchantRepository,
        WarehouseRepository $warehouseRepository,
        ProductSkuRepository $skuRepository,
    ): JsonResponse {
        $merchant = $merchantRepository->findByUser($user);
        if (!$merchant) {
            throw $this->createAccessDeniedException('Merchant not found');
        }

        $warehouse = $warehouseRepository->find($request->warehouseId);
        if (!$warehouse) {
            return $this->json(['error' => 'Warehouse not found'], 404);
        }

        // 验证仓库是商户的逻辑仓库
        if (!$warehouse->isMerchantWarehouse()) {
            return $this->json(['error' => 'Only merchant warehouses can use this operation'], 400);
        }

        if ($warehouse->getMerchant()?->getId() !== $merchant->getId()) {
            return $this->json(['error' => 'Warehouse does not belong to merchant'], 403);
        }

        $sku = $skuRepository->find($request->productSkuId);
        if (!$sku) {
            return $this->json(['error' => 'Product SKU not found'], 404);
        }

        // DEBUG: Log costCurrency value
        error_log('DEBUG: costCurrency from DTO = ' . var_export($request->costCurrency, true));

        try {
            $inventory = $this->inventoryService->initializeInventory(
                $merchant,
                $warehouse,
                $sku,
                $request->quantity,
                $request->unitCost,
                $request->costCurrency,
                $request->notes,
                $user->getId(),
                $user->getEmail()
            );

            return $this->json([
                'message' => 'Inventory created successfully',
                'data' => $this->formatInventory($inventory),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * 调整库存（逻辑仓库）.
     */
    #[Route('/{id}/adjust', name: 'merchant_inventory_adjust', methods: ['PUT'])]
    public function adjust(
        string $id,
        #[MapRequestPayload] AdjustInventoryRequest $request,
        #[CurrentUser] User $user,
        MerchantRepository $merchantRepository,
        MerchantInventoryRepository $inventoryRepository,
    ): JsonResponse {
        $merchant = $merchantRepository->findByUser($user);
        if (!$merchant) {
            throw $this->createAccessDeniedException('Merchant not found');
        }

        $inventory = $inventoryRepository->find($id);
        if (!$inventory) {
            return $this->json(['error' => 'Inventory not found'], 404);
        }

        // 验证库存属于该商户
        if ($inventory->getMerchant()->getId() !== $merchant->getId()) {
            return $this->json(['error' => 'Inventory does not belong to merchant'], 403);
        }

        // 验证是逻辑仓库
        if (!$inventory->isMerchantOwned()) {
            return $this->json(['error' => 'Only merchant warehouse inventory can be adjusted'], 400);
        }

        try {
            $inventory = $this->inventoryService->adjustInventory(
                $inventory,
                $request->adjustmentType,
                $request->quantity,
                $request->unitCost,
                $request->notes,
                $user->getId(),
                $user->getEmail()
            );

            return $this->json([
                'message' => 'Inventory adjusted successfully',
                'data' => $this->formatInventory($inventory),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * 获取商户的逻辑仓库列表（用于添加库存时选择）.
     */
    #[Route('/merchant-warehouses', name: 'merchant_inventory_merchant_warehouses', methods: ['GET'])]
    public function merchantWarehouses(
        #[CurrentUser] User $user,
        MerchantRepository $merchantRepository,
        WarehouseRepository $warehouseRepository,
    ): JsonResponse {
        $merchant = $merchantRepository->findByUser($user);
        if (!$merchant) {
            throw $this->createAccessDeniedException('Merchant not found');
        }

        $warehouses = $warehouseRepository->findBy([
            'merchant' => $merchant,
            'category' => 'merchant',
            'status' => 'active',
        ]);

        return $this->json([
            'data' => array_map(fn ($w) => [
                'id' => $w->getId(),
                'code' => $w->getCode(),
                'name' => $w->getName(),
                'shortName' => $w->getShortName(),
            ], $warehouses),
        ]);
    }

    /**
     * 下载导入模板.
     */
    #[Route('/import-template', name: 'merchant_inventory_import_template', methods: ['GET'])]
    public function importTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 设置表头
        $sheet->setCellValue('A1', 'SKU编码');
        $sheet->setCellValue('B1', '数量');
        $sheet->setCellValue('C1', '单位成本（选填）');
        $sheet->setCellValue('D1', '成本币种（选填，默认CNY）');

        // 添加示例数据
        $sheet->setCellValue('A2', 'ABC123-42');
        $sheet->setCellValue('B2', '100');
        $sheet->setCellValue('C2', '99.00');
        $sheet->setCellValue('D2', 'CNY');

        // 设置列宽
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(25);

        // 设置表头样式
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);

        $response = new StreamedResponse(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="inventory_import_template.xlsx"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    /**
     * 预览导入文件.
     */
    #[Route('/import-preview', name: 'merchant_inventory_import_preview', methods: ['POST'])]
    public function importPreview(
        Request $request,
        #[CurrentUser] User $user,
        MerchantRepository $merchantRepository,
        WarehouseRepository $warehouseRepository,
        ProductSkuRepository $skuRepository,
        MerchantInventoryRepository $inventoryRepository,
    ): JsonResponse {
        $merchant = $merchantRepository->findByUser($user);
        if (!$merchant) {
            throw $this->createAccessDeniedException('Merchant not found');
        }

        // 获取上传的文件
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'No file uploaded'], 400);
        }

        // 获取仓库ID
        $warehouseId = $request->request->get('warehouseId');
        if (!$warehouseId) {
            return $this->json(['error' => 'Warehouse ID is required'], 400);
        }

        $warehouse = $warehouseRepository->find($warehouseId);
        if (!$warehouse) {
            return $this->json(['error' => 'Warehouse not found'], 404);
        }

        // 验证仓库是商户的逻辑仓库
        if (!$warehouse->isMerchantWarehouse()) {
            return $this->json(['error' => 'Only merchant warehouses can use this operation'], 400);
        }

        if ($warehouse->getMerchant()?->getId() !== $merchant->getId()) {
            return $this->json(['error' => 'Warehouse does not belong to merchant'], 403);
        }

        // 解析 Excel
        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to parse Excel file: '.$e->getMessage()], 400);
        }

        // 跳过表头
        array_shift($rows);

        $items = [];
        $validCount = 0;
        $invalidCount = 0;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // 从第2行开始（因为第1行是表头）
            $skuCode = trim((string) ($row[0] ?? ''));
            $quantity = (int) ($row[1] ?? 0);
            $unitCost = !empty($row[2]) ? (string) $row[2] : null;
            $costCurrency = !empty($row[3]) ? trim((string) $row[3]) : 'CNY';

            // 跳过空行
            if (empty($skuCode)) {
                continue;
            }

            // 验证数量
            if ($quantity <= 0) {
                $items[] = new ImportInventoryItemDto(
                    rowNumber: $rowNumber,
                    skuCode: $skuCode,
                    quantity: $quantity,
                    unitCost: $unitCost,
                    costCurrency: $costCurrency,
                    isValid: false,
                    errorMessage: 'Quantity must be greater than 0'
                );
                ++$invalidCount;
                continue;
            }

            // 查找 SKU
            $sku = $skuRepository->findOneBySkuCode($skuCode);
            if (!$sku) {
                $items[] = new ImportInventoryItemDto(
                    rowNumber: $rowNumber,
                    skuCode: $skuCode,
                    quantity: $quantity,
                    unitCost: $unitCost,
                    costCurrency: $costCurrency,
                    isValid: false,
                    errorMessage: 'SKU not found'
                );
                ++$invalidCount;
                continue;
            }

            // 检查是否已存在库存
            $existingInventory = $inventoryRepository->findOneBy([
                'merchant' => $merchant,
                'warehouse' => $warehouse,
                'productSku' => $sku,
            ]);

            $product = $sku->getProduct();
            $items[] = new ImportInventoryItemDto(
                rowNumber: $rowNumber,
                skuCode: $skuCode,
                quantity: $quantity,
                unitCost: $unitCost,
                costCurrency: $costCurrency,
                isValid: true,
                errorMessage: null,
                productSkuId: $sku->getId(),
                productName: $product->getName(),
                skuName: $sku->getSkuName(),
                exists: $existingInventory !== null
            );
            ++$validCount;
        }

        return $this->json([
            'data' => [
                'items' => array_map(fn (ImportInventoryItemDto $item) => $item->toArray(), $items),
                'summary' => [
                    'total' => count($items),
                    'valid' => $validCount,
                    'invalid' => $invalidCount,
                ],
            ],
        ]);
    }

    /**
     * 确认导入.
     */
    #[Route('/import', name: 'merchant_inventory_import', methods: ['POST'])]
    public function import(
        #[MapRequestPayload] ImportInventoryConfirmRequest $request,
        #[CurrentUser] User $user,
        MerchantRepository $merchantRepository,
        WarehouseRepository $warehouseRepository,
    ): JsonResponse {
        $merchant = $merchantRepository->findByUser($user);
        if (!$merchant) {
            throw $this->createAccessDeniedException('Merchant not found');
        }

        $warehouse = $warehouseRepository->find($request->warehouseId);
        if (!$warehouse) {
            return $this->json(['error' => 'Warehouse not found'], 404);
        }

        // 验证仓库是商户的逻辑仓库
        if (!$warehouse->isMerchantWarehouse()) {
            return $this->json(['error' => 'Only merchant warehouses can use this operation'], 400);
        }

        if ($warehouse->getMerchant()?->getId() !== $merchant->getId()) {
            return $this->json(['error' => 'Warehouse does not belong to merchant'], 403);
        }

        if (empty($request->items)) {
            return $this->json(['error' => 'No items to import'], 400);
        }

        try {
            $result = $this->inventoryService->batchImportInventory(
                $merchant,
                $warehouse,
                $request->items,
                $request->conflictStrategy,
                $user->getId(),
                $user->getEmail()
            );

            return $this->json([
                'message' => 'Import completed',
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
