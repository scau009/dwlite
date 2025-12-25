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

    /**
     * 统计仓库各状态的出库单数量
     */
    public function countByWarehouseGroupByStatus(Warehouse $warehouse): array
    {
        $results = $this->createQueryBuilder('o')
            ->select('o.status, COUNT(o.id) as count')
            ->andWhere('o.warehouse = :warehouse')
            ->setParameter('warehouse', $warehouse)
            ->groupBy('o.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }
        return $counts;
    }

    /**
     * 统计仓库今日已发货的出库单数量
     */
    public function countShippedTodayByWarehouse(Warehouse $warehouse): int
    {
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Shanghai'));
        $tomorrow = $today->modify('+1 day');

        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.warehouse = :warehouse')
            ->andWhere('o.status = :status')
            ->andWhere('o.shippedAt >= :today')
            ->andWhere('o.shippedAt < :tomorrow')
            ->setParameter('warehouse', $warehouse)
            ->setParameter('status', OutboundOrder::STATUS_SHIPPED)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * 获取仓库近N天每日发货的出库单数量
     *
     * @return array<string, int> 日期 => 数量
     */
    public function countShippedByWarehouseGroupByDate(Warehouse $warehouse, int $days = 7): array
    {
        $startDate = new \DateTimeImmutable("-" . ($days - 1) . " days", new \DateTimeZone('Asia/Shanghai'));
        $startDate = $startDate->setTime(0, 0, 0);

        $conn = $this->getEntityManager()->getConnection();
        $sql = '
            SELECT DATE(shipped_at) as date, COUNT(id) as count
            FROM outbound_orders
            WHERE warehouse_id = :warehouseId
            AND status = :status
            AND shipped_at >= :startDate
            GROUP BY DATE(shipped_at)
        ';

        $results = $conn->executeQuery($sql, [
            'warehouseId' => $warehouse->getId(),
            'status' => OutboundOrder::STATUS_SHIPPED,
            'startDate' => $startDate->format('Y-m-d H:i:s'),
        ])->fetchAllAssociative();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['date']] = (int) $row['count'];
        }
        return $counts;
    }
}