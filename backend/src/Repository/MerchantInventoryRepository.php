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
}
