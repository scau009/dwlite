<?php

namespace App\Repository;

use App\Entity\Merchant;
use App\Entity\MerchantInventory;
use App\Entity\ProductSku;
use App\Entity\Warehouse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MerchantInventory>
 */
class MerchantInventoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MerchantInventory::class);
    }

    /**
     * 获取或创建库存记录
     */
    public function findOrCreate(Merchant $merchant, Warehouse $warehouse, ProductSku $sku): MerchantInventory
    {
        $inventory = $this->findOneBy([
            'merchant' => $merchant,
            'warehouse' => $warehouse,
            'productSku' => $sku,
        ]);

        if ($inventory === null) {
            $inventory = new MerchantInventory();
            $inventory->setMerchant($merchant);
            $inventory->setWarehouse($warehouse);
            $inventory->setProductSku($sku);
        }

        return $inventory;
    }

    /**
     * 获取商户在某仓库的所有库存
     *
     * @return MerchantInventory[]
     */
    public function findByMerchantAndWarehouse(Merchant $merchant, Warehouse $warehouse): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.merchant = :merchant')
            ->andWhere('i.warehouse = :warehouse')
            ->setParameter('merchant', $merchant)
            ->setParameter('warehouse', $warehouse)
            ->orderBy('i.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取商户所有库存
     *
     * @return MerchantInventory[]
     */
    public function findByMerchant(Merchant $merchant): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->orderBy('i.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取低于安全库存的记录
     *
     * @return MerchantInventory[]
     */
    public function findBelowSafetyStock(Merchant $merchant): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.merchant = :merchant')
            ->andWhere('i.safetyStock IS NOT NULL')
            ->andWhere('i.quantityAvailable < i.safetyStock')
            ->setParameter('merchant', $merchant)
            ->orderBy('i.quantityAvailable', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取商户某 SKU 在所有仓库的库存汇总
     */
    public function getSkuTotalInventory(Merchant $merchant, ProductSku $sku): array
    {
        return $this->createQueryBuilder('i')
            ->select('SUM(i.quantityInTransit) as inTransit')
            ->addSelect('SUM(i.quantityAvailable) as available')
            ->addSelect('SUM(i.quantityReserved) as reserved')
            ->addSelect('SUM(i.quantityDamaged) as damaged')
            ->andWhere('i.merchant = :merchant')
            ->andWhere('i.productSku = :sku')
            ->setParameter('merchant', $merchant)
            ->setParameter('sku', $sku)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * 获取有库存的 SKU 列表（用于销售）
     *
     * @return MerchantInventory[]
     */
    public function findWithAvailableStock(Merchant $merchant, ?Warehouse $warehouse = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.merchant = :merchant')
            ->andWhere('i.quantityAvailable > 0')
            ->setParameter('merchant', $merchant);

        if ($warehouse !== null) {
            $qb->andWhere('i.warehouse = :warehouse')
                ->setParameter('warehouse', $warehouse);
        }

        return $qb->orderBy('i.quantityAvailable', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 按仓库分页查询库存
     *
     * @return array{data: MerchantInventory[], meta: array{total: int, page: int, limit: int, pages: int}}
     */
    public function findByWarehousePaginated(
        Warehouse $warehouse,
        int $page = 1,
        int $limit = 20,
        array $filters = []
    ): array {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.productSku', 'sku')
            ->leftJoin('sku.product', 'p')
            ->andWhere('i.warehouse = :warehouse')
            ->setParameter('warehouse', $warehouse)
            ->orderBy('i.updatedAt', 'DESC');

        // 搜索商品名或 SKU 名
        if (!empty($filters['search'])) {
            $qb->andWhere('sku.skuName LIKE :search OR p.name LIKE :search OR p.styleNumber LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // 只显示有库存的
        if (!empty($filters['hasStock'])) {
            $qb->andWhere('i.quantityInTransit > 0 OR i.quantityAvailable > 0 OR i.quantityReserved > 0');
        }

        // 计算总数
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // 分页
        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $data = $qb->getQuery()->getResult();

        return [
            'data' => $data,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => (int) ceil($total / $limit),
            ],
        ];
    }

    /**
     * 获取仓库库存汇总
     */
    public function getWarehouseSummary(Warehouse $warehouse): array
    {
        return $this->createQueryBuilder('i')
            ->select('SUM(i.quantityInTransit) as totalInTransit')
            ->addSelect('SUM(i.quantityAvailable) as totalAvailable')
            ->addSelect('SUM(i.quantityReserved) as totalReserved')
            ->addSelect('SUM(i.quantityDamaged) as totalDamaged')
            ->addSelect('COUNT(DISTINCT i.productSku) as totalSkuCount')
            ->andWhere('i.warehouse = :warehouse')
            ->setParameter('warehouse', $warehouse)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * 按商户分页查询库存
     *
     * @return array{data: MerchantInventory[], meta: array{total: int, page: int, limit: int, pages: int}}
     */
    public function findByMerchantPaginated(
        Merchant $merchant,
        int $page = 1,
        int $limit = 20,
        array $filters = []
    ): array {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.productSku', 'sku')
            ->leftJoin('sku.product', 'p')
            ->leftJoin('i.warehouse', 'w')
            ->andWhere('i.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->orderBy('i.updatedAt', 'DESC');

        // 搜索商品名或 SKU 名
        if (!empty($filters['search'])) {
            $qb->andWhere('sku.skuName LIKE :search OR p.name LIKE :search OR p.styleNumber LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // 按仓库筛选
        if (!empty($filters['warehouseId'])) {
            $qb->andWhere('i.warehouse = :warehouseId')
                ->setParameter('warehouseId', $filters['warehouseId']);
        }

        // 库存状态筛选
        if (!empty($filters['stockStatus'])) {
            switch ($filters['stockStatus']) {
                case 'in_transit':
                    $qb->andWhere('i.quantityInTransit > 0');
                    break;
                case 'available':
                    $qb->andWhere('i.quantityAvailable > 0');
                    break;
                case 'reserved':
                    $qb->andWhere('i.quantityReserved > 0');
                    break;
                case 'damaged':
                    $qb->andWhere('i.quantityDamaged > 0');
                    break;
                case 'has_stock':
                    $qb->andWhere('i.quantityInTransit > 0 OR i.quantityAvailable > 0 OR i.quantityReserved > 0');
                    break;
                case 'low_stock':
                    $qb->andWhere('i.safetyStock IS NOT NULL AND i.quantityAvailable < i.safetyStock');
                    break;
            }
        }

        // 只显示有库存的
        if (!empty($filters['hasStock'])) {
            $qb->andWhere('i.quantityInTransit > 0 OR i.quantityAvailable > 0 OR i.quantityReserved > 0');
        }

        // 计算总数
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // 分页
        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $data = $qb->getQuery()->getResult();

        return [
            'data' => $data,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => (int) ceil($total / $limit),
            ],
        ];
    }

    /**
     * 获取商户库存汇总
     */
    public function getMerchantSummary(Merchant $merchant): array
    {
        return $this->createQueryBuilder('i')
            ->select('SUM(i.quantityInTransit) as totalInTransit')
            ->addSelect('SUM(i.quantityAvailable) as totalAvailable')
            ->addSelect('SUM(i.quantityReserved) as totalReserved')
            ->addSelect('SUM(i.quantityDamaged) as totalDamaged')
            ->addSelect('COUNT(DISTINCT i.productSku) as totalSkuCount')
            ->addSelect('COUNT(DISTINCT i.warehouse) as warehouseCount')
            ->andWhere('i.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->getQuery()
            ->getSingleResult();
    }
}
