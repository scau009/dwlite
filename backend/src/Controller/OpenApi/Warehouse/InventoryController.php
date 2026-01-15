<?php

namespace App\Controller\OpenApi\Warehouse;

use App\Attribute\OpenApiOnly;
use App\Dto\OpenApi\Warehouse\AdjustmentRequest;
use App\Dto\OpenApi\Warehouse\InventoryQuery;
use App\Dto\OpenApi\Warehouse\StocktakeRequest;
use App\Entity\InventoryTransaction;
use App\Entity\MerchantInventory;
use App\Entity\Warehouse;
use App\Repository\MerchantInventoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Warehouse Inventory API Controller.
 */
#[Route('/api/v1/open/warehouse/inventory', name: 'open_api_warehouse_inventory_')]
#[OpenApiOnly(permission: 'inventory:read')]
class InventoryController extends AbstractController
{
    public function __construct(
        private readonly MerchantInventoryRepository $inventoryRepository,
        private readonly EntityManagerInterface $em
    ) {
    }

    /**
     * List inventory.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        #[MapQueryString] InventoryQuery $query,
        Request $request
    ): JsonResponse {
        /** @var Warehouse $warehouse */
        $warehouse = $request->attributes->get('warehouse');

        // Build query
        $qb = $this->inventoryRepository->createQueryBuilder('i')
            ->join('i.productSku', 'sku')
            ->where('i.warehouse = :warehouse')
            ->setParameter('warehouse', $warehouse)
            ->orderBy('i.updatedAt', 'DESC');

        // Apply filters
        if ($query->sku !== null) {
            $qb->andWhere('sku.sku LIKE :sku')
                ->setParameter('sku', '%'.$query->sku.'%');
        }

        if ($query->merchantCode !== null) {
            $qb->join('i.merchant', 'm')
                ->andWhere('m.code = :merchantCode')
                ->setParameter('merchantCode', $query->merchantCode);
        }

        if ($query->lowStock === true) {
            $qb->andWhere('i.quantityAvailable < i.safetyStock');
        }

        if ($query->outOfStock === true) {
            $qb->andWhere('i.quantityAvailable = 0');
        }

        // Count total
        $totalCount = (int) (clone $qb)
            ->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Paginate
        $inventories = $qb
            ->setFirstResult(($query->page - 1) * $query->pageSize)
            ->setMaxResults($query->pageSize)
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'data' => [
                'items' => array_map(fn(MerchantInventory $inv) => $this->serializeInventory($inv), $inventories),
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
     * Submit stocktake results.
     */
    #[Route('/stocktake', name: 'stocktake', methods: ['POST'])]
    #[OpenApiOnly(permission: 'inventory:write')]
    public function stocktake(
        #[MapRequestPayload] StocktakeRequest $dto,
        Request $request
    ): JsonResponse {
        /** @var Warehouse $warehouse */
        $warehouse = $request->attributes->get('warehouse');

        $results = [];
        foreach ($dto->items as $item) {
            // Find inventory by SKU
            $inventory = $this->inventoryRepository->createQueryBuilder('i')
                ->join('i.productSku', 'sku')
                ->where('i.warehouse = :warehouse')
                ->andWhere('sku.sku = :sku')
                ->setParameter('warehouse', $warehouse)
                ->setParameter('sku', $item->sku)
                ->getQuery()
                ->getOneOrNullResult();

            if ($inventory === null) {
                $results[] = [
                    'sku' => $item->sku,
                    'status' => 'error',
                    'message' => 'SKU not found in warehouse inventory',
                ];
                continue;
            }

            // Calculate adjustment
            $currentQty = $inventory->getQuantityAvailable();
            $adjustment = $item->countedQuantity - $currentQty;

            if ($adjustment === 0) {
                $results[] = [
                    'sku' => $item->sku,
                    'status' => 'no_change',
                    'countedQuantity' => $item->countedQuantity,
                    'previousQuantity' => $currentQty,
                    'adjustment' => 0,
                ];
                continue;
            }

            // Update inventory
            $inventory->setQuantityAvailable($item->countedQuantity);

            // Create transaction record
            $transaction = new InventoryTransaction();
            $transaction->setMerchantInventory($inventory);
            $transaction->setType($adjustment > 0 ? InventoryTransaction::TYPE_ADJUSTMENT_IN : InventoryTransaction::TYPE_ADJUSTMENT_OUT);
            $transaction->setQuantityChange(abs($adjustment));
            $transaction->setQuantityBefore($currentQty);
            $transaction->setQuantityAfter($item->countedQuantity);
            $transaction->setReason('stocktake');
            if ($item->notes !== null) {
                $transaction->setNotes($item->notes);
            }

            $this->em->persist($transaction);

            $results[] = [
                'sku' => $item->sku,
                'status' => 'adjusted',
                'countedQuantity' => $item->countedQuantity,
                'previousQuantity' => $currentQty,
                'adjustment' => $adjustment,
            ];
        }

        $this->em->flush();

        return $this->json([
            'success' => true,
            'data' => [
                'results' => $results,
                'countedAt' => $dto->countedAt,
            ],
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * Submit inventory adjustment.
     */
    #[Route('/adjustment', name: 'adjustment', methods: ['POST'])]
    #[OpenApiOnly(permission: 'inventory:write')]
    public function adjustment(
        #[MapRequestPayload] AdjustmentRequest $dto,
        Request $request
    ): JsonResponse {
        /** @var Warehouse $warehouse */
        $warehouse = $request->attributes->get('warehouse');

        $results = [];
        foreach ($dto->items as $item) {
            // Find inventory by SKU
            $inventory = $this->inventoryRepository->createQueryBuilder('i')
                ->join('i.productSku', 'sku')
                ->where('i.warehouse = :warehouse')
                ->andWhere('sku.sku = :sku')
                ->setParameter('warehouse', $warehouse)
                ->setParameter('sku', $item->sku)
                ->getQuery()
                ->getOneOrNullResult();

            if ($inventory === null) {
                $results[] = [
                    'sku' => $item->sku,
                    'status' => 'error',
                    'message' => 'SKU not found in warehouse inventory',
                ];
                continue;
            }

            $currentQty = $inventory->getQuantityAvailable();
            $newQty = $currentQty + $item->adjustmentQuantity;

            if ($newQty < 0) {
                $results[] = [
                    'sku' => $item->sku,
                    'status' => 'error',
                    'message' => 'Adjustment would result in negative inventory',
                ];
                continue;
            }

            // Update inventory
            $inventory->setQuantityAvailable($newQty);

            // Create transaction record
            $transaction = new InventoryTransaction();
            $transaction->setMerchantInventory($inventory);
            $transaction->setType($item->adjustmentQuantity > 0 ? InventoryTransaction::TYPE_ADJUSTMENT_IN : InventoryTransaction::TYPE_ADJUSTMENT_OUT);
            $transaction->setQuantityChange(abs($item->adjustmentQuantity));
            $transaction->setQuantityBefore($currentQty);
            $transaction->setQuantityAfter($newQty);
            $transaction->setReason($item->reason);
            if ($item->notes !== null) {
                $transaction->setNotes($item->notes);
            }

            $this->em->persist($transaction);

            $results[] = [
                'sku' => $item->sku,
                'status' => 'success',
                'adjustment' => $item->adjustmentQuantity,
                'previousQuantity' => $currentQty,
                'newQuantity' => $newQty,
            ];
        }

        $this->em->flush();

        return $this->json([
            'success' => true,
            'data' => [
                'results' => $results,
                'adjustedAt' => $dto->adjustedAt,
            ],
            'requestId' => $request->attributes->get('request_id'),
        ]);
    }

    /**
     * Serialize inventory for response.
     */
    private function serializeInventory(MerchantInventory $inventory): array
    {
        $sku = $inventory->getProductSku();

        return [
            'sku' => $sku->getSku(),
            'merchantCode' => $inventory->getMerchant()->getCode(),
            'merchantName' => $inventory->getMerchant()->getName(),
            'quantityInTransit' => $inventory->getQuantityInTransit(),
            'quantityAvailable' => $inventory->getQuantityAvailable(),
            'quantityReserved' => $inventory->getQuantityReserved(),
            'quantityDamaged' => $inventory->getQuantityDamaged(),
            'safetyStock' => $inventory->getSafetyStock(),
            'lastInboundAt' => $inventory->getLastInboundAt()?->format(\DateTimeInterface::ATOM),
            'lastOutboundAt' => $inventory->getLastOutboundAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $inventory->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
