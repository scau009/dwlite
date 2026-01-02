<?php

namespace App\Repository;

use App\Entity\SalesChannel;
use App\Entity\SalesChannelWarehouse;
use App\Entity\Warehouse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SalesChannelWarehouse>
 */
class SalesChannelWarehouseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SalesChannelWarehouse::class);
    }

    public function save(SalesChannelWarehouse $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SalesChannelWarehouse $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 获取销售渠道的所有履约仓库（按优先级排序）
     *
     * @return SalesChannelWarehouse[]
     */
    public function findByChannel(SalesChannel $channel, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('scw')
            ->leftJoin('scw.warehouse', 'w')
            ->addSelect('w')
            ->where('scw.salesChannel = :channel')
            ->setParameter('channel', $channel)
            ->orderBy('scw.priority', 'ASC')
            ->addOrderBy('scw.createdAt', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('scw.status = :status')
                ->andWhere('w.status = :warehouseStatus')
                ->setParameter('status', SalesChannelWarehouse::STATUS_ACTIVE)
                ->setParameter('warehouseStatus', Warehouse::STATUS_ACTIVE);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 获取仓库关联的所有销售渠道
     *
     * @return SalesChannelWarehouse[]
     */
    public function findByWarehouse(Warehouse $warehouse, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('scw')
            ->leftJoin('scw.salesChannel', 'sc')
            ->addSelect('sc')
            ->where('scw.warehouse = :warehouse')
            ->setParameter('warehouse', $warehouse)
            ->orderBy('scw.priority', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('scw.status = :status')
                ->andWhere('sc.status = :channelStatus')
                ->setParameter('status', SalesChannelWarehouse::STATUS_ACTIVE)
                ->setParameter('channelStatus', SalesChannel::STATUS_ACTIVE);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 查找特定销售渠道和仓库的关联记录
     */
    public function findOneByChannelAndWarehouse(SalesChannel $channel, Warehouse $warehouse): ?SalesChannelWarehouse
    {
        return $this->createQueryBuilder('scw')
            ->where('scw.salesChannel = :channel')
            ->andWhere('scw.warehouse = :warehouse')
            ->setParameter('channel', $channel)
            ->setParameter('warehouse', $warehouse)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 获取销售渠道的可用仓库列表（仅返回 Warehouse 实体）
     *
     * @return Warehouse[]
     */
    public function findAvailableWarehousesByChannel(SalesChannel $channel): array
    {
        $channelWarehouses = $this->findByChannel($channel, true);

        return array_map(
            fn (SalesChannelWarehouse $scw) => $scw->getWarehouse(),
            $channelWarehouses
        );
    }

    /**
     * 检查销售渠道是否配置了某个仓库
     */
    public function hasWarehouse(SalesChannel $channel, Warehouse $warehouse): bool
    {
        $count = $this->createQueryBuilder('scw')
            ->select('COUNT(scw.id)')
            ->where('scw.salesChannel = :channel')
            ->andWhere('scw.warehouse = :warehouse')
            ->andWhere('scw.status = :status')
            ->setParameter('channel', $channel)
            ->setParameter('warehouse', $warehouse)
            ->setParameter('status', SalesChannelWarehouse::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * 获取下一个优先级值（用于添加新仓库时）
     */
    public function getNextPriority(SalesChannel $channel): int
    {
        $maxPriority = $this->createQueryBuilder('scw')
            ->select('MAX(scw.priority)')
            ->where('scw.salesChannel = :channel')
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getSingleScalarResult();

        return ($maxPriority ?? -1) + 1;
    }
}