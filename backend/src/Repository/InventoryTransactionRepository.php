<?php

namespace App\Repository;

use App\Entity\InventoryTransaction;
use App\Entity\MerchantInventory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryTransaction>
 */
class InventoryTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryTransaction::class);
    }

    /**
     * 获取库存的流水记录.
     *
     * @return InventoryTransaction[]
     */
    public function findByInventory(MerchantInventory $inventory, int $limit = 100): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.merchantInventory = :inventory')
            ->setParameter('inventory', $inventory)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * 按关联单据查找流水.
     *
     * @return InventoryTransaction[]
     */
    public function findByReference(string $referenceType, string $referenceId): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.referenceType = :type')
            ->andWhere('t.referenceId = :id')
            ->setParameter('type', $referenceType)
            ->setParameter('id', $referenceId)
            ->orderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取某时间段内的流水.
     *
     * @return InventoryTransaction[]
     */
    public function findByDateRange(
        MerchantInventory $inventory,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        return $this->createQueryBuilder('t')
            ->andWhere('t.merchantInventory = :inventory')
            ->andWhere('t.createdAt >= :startDate')
            ->andWhere('t.createdAt <= :endDate')
            ->setParameter('inventory', $inventory)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
