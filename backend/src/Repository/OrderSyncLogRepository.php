<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderSyncLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderSyncLog>
 */
class OrderSyncLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderSyncLog::class);
    }

    /**
     * 查找订单的最新同步日志.
     */
    public function findLatestByOrder(Order $order, ?string $direction = null): ?OrderSyncLog
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.orderId = :orderId')
            ->setParameter('orderId', $order->getId())
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults(1);

        if ($direction !== null) {
            $qb->andWhere('l.direction = :direction')
                ->setParameter('direction', $direction);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * 查找订单的最新同步日志（按操作类型）.
     */
    public function findLatestByOrderAndOperation(Order $order, string $operation): ?OrderSyncLog
    {
        return $this->createQueryBuilder('l')
            ->where('l.orderId = :orderId')
            ->andWhere('l.operation = :operation')
            ->setParameter('orderId', $order->getId())
            ->setParameter('operation', $operation)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 统计指定状态的日志数量.
     */
    public function countByStatus(string $status, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.status = :status')
            ->andWhere('l.createdAt >= :since')
            ->setParameter('status', $status)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * 统计指定渠道的同步日志.
     *
     * @return array{total: int, success: int, failed: int}
     */
    public function countBySalesChannel(string $salesChannelId, \DateTimeImmutable $since): array
    {
        $result = $this->createQueryBuilder('l')
            ->select(
                'COUNT(l.id) as total',
                "SUM(CASE WHEN l.status = 'success' THEN 1 ELSE 0 END) as success",
                "SUM(CASE WHEN l.status = 'failed' THEN 1 ELSE 0 END) as failed"
            )
            ->where('l.salesChannelId = :channelId')
            ->andWhere('l.createdAt >= :since')
            ->setParameter('channelId', $salesChannelId)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) $result['total'],
            'success' => (int) $result['success'],
            'failed' => (int) $result['failed'],
        ];
    }

    /**
     * 查找指定外部订单ID的日志.
     *
     * @return OrderSyncLog[]
     */
    public function findByExternalOrderId(string $externalOrderId, int $limit = 10): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.externalOrderId = :externalOrderId')
            ->setParameter('externalOrderId', $externalOrderId)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
