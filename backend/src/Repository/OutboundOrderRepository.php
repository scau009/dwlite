<?php

namespace App\Repository;

use App\Entity\OutboundOrder;
use App\Entity\Warehouse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OutboundOrder>
 */
class OutboundOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OutboundOrder::class);
    }

    /**
     * 获取待同步到 WMS 的出库单
     * @return OutboundOrder[]
     */
    public function findPendingSync(int $limit = 50): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.syncStatus IN (:statuses)')
            ->andWhere('o.status = :status')
            ->setParameter('statuses', [OutboundOrder::SYNC_PENDING, OutboundOrder::SYNC_FAILED])
            ->setParameter('status', OutboundOrder::STATUS_PENDING)
            ->orderBy('o.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * 根据仓库获取待处理的出库单
     * @return OutboundOrder[]
     */
    public function findPendingByWarehouse(Warehouse $warehouse): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.warehouse = :warehouse')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('warehouse', $warehouse)
            ->setParameter('statuses', [
                OutboundOrder::STATUS_PENDING,
                OutboundOrder::STATUS_PICKING,
                OutboundOrder::STATUS_PACKING,
                OutboundOrder::STATUS_READY,
            ])
            ->orderBy('o.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByExternalId(string $externalId): ?OutboundOrder
    {
        return $this->findOneBy(['externalId' => $externalId]);
    }
}