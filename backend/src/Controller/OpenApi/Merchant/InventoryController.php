<?php

namespace App\Controller\OpenApi\Merchant;

use App\Attribute\OpenApiOnly;
use App\Dto\OpenApi\Merchant\InventoryQuery;
use App\Entity\Merchant;
use App\Entity\MerchantInventory;
use App\Repository\MerchantInventoryRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Merchant Inventory API Controller.
 */
#[Route('/api/v1/open/merchant/inventory', name: 'open_api_merchant_inventory_')]
#[OpenApiOnly(permission: 'merchant_inventory:read')]
#[OA\Tag(name: 'Merchant - Inventory', description: '商户库存查询')]
class InventoryController extends AbstractController
{
    public function __construct(
        private readonly MerchantInventoryRepository $inventoryRepository
    ) {
    }

    /**
     * List merchant inventory.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        #[MapQueryString] InventoryQuery $query,
        Request $request
    ): JsonResponse {
        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('merchant');

        $qb = $this->inventoryRepository->createQueryBuilder('i')
            ->join('i.productSku', 'sku')
            ->where('i.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->orderBy('i.updatedAt', 'DESC');

        if ($query->sku !== null) {
            $qb->andWhere('sku.sku LIKE :sku')
                ->setParameter('sku', '%'.$query->sku.'%');
        }

        if ($query->warehouseCode !== null) {
            $qb->join('i.warehouse', 'w')
                ->andWhere('w.code = :warehouseCode')
                ->setParameter('warehouseCode', $query->warehouseCode);
        }

        if ($query->lowStock === true) {
            $qb->andWhere('i.quantityAvailable < i.safetyStock');
        }

        if ($query->outOfStock === true) {
            $qb->andWhere('i.quantityAvailable = 0');
        }

        $totalCount = (int) (clone $qb)->select('COUNT(i.id)')->getQuery()->getSingleScalarResult();

        $inventories = $qb
            ->setFirstResult(($query->page - 1) * $query->pageSize)
            ->setMaxResults($query->pageSize)
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'data' => [
                'items' => array_map(fn (MerchantInventory $inv) => [
                    'sku' => $inv->getProductSku()->getSkuName(),
                    'warehouseCode' => $inv->getWarehouse()->getCode(),
                    'warehouseName' => $inv->getWarehouse()->getName(),
                    'quantityInTransit' => $inv->getQuantityInTransit(),
                    'quantityAvailable' => $inv->getQuantityAvailable(),
                    'quantityReserved' => $inv->getQuantityReserved(),
                    'quantityAllocated' => $inv->getQuantityAllocated(),
                    'safetyStock' => $inv->getSafetyStock(),
                    'lastInboundAt' => $inv->getLastInboundAt()?->format(\DateTimeInterface::ATOM),
                    'lastOutboundAt' => $inv->getLastOutboundAt()?->format(\DateTimeInterface::ATOM),
                    'updatedAt' => $inv->getUpdatedAt()->format(\DateTimeInterface::ATOM),
                ], $inventories),
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
}
