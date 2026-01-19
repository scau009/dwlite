<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FulfillmentAllocationLog;
use App\Entity\Order;
use App\Entity\OrderItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FulfillmentAllocationLog>
 */
class FulfillmentAllocationLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FulfillmentAllocationLog::class);
    }

    /**
     * 获取订单的分配日志.
     *
     * @return FulfillmentAllocationLog[]
     */
    public function findByOrder(Order $order): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.order = :order')
            ->setParameter('order', $order)
            ->orderBy('l.attemptNumber', 'ASC')
            ->addOrderBy('l.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取订单项的分配日志.
     *
     * @return FulfillmentAllocationLog[]
     */
    public function findByOrderItem(OrderItem $orderItem): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.orderItem = :orderItem')
            ->setParameter('orderItem', $orderItem)
            ->orderBy('l.attemptNumber', 'ASC')
            ->addOrderBy('l.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取订单的最后一次分配尝试.
     */
    public function findLastAttemptByOrder(Order $order): ?FulfillmentAllocationLog
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.order = :order')
            ->setParameter('order', $order)
            ->orderBy('l.attemptNumber', 'DESC')
            ->addOrderBy('l.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 统计订单的分配尝试次数.
     */
    public function countAttemptsByOrder(Order $order): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(DISTINCT l.attemptNumber)')
            ->andWhere('l.order = :order')
            ->setParameter('order', $order)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * 按结果统计分配日志.
     *
     * @return array<string, int>
     */
    public function countByResult(): array
    {
        $results = $this->createQueryBuilder('l')
            ->select('l.result, COUNT(l.id) as count')
            ->groupBy('l.result')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['result']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * 获取成功分配日志.
     *
     * @return FulfillmentAllocationLog[]
     */
    public function findSuccessfulByOrder(Order $order): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.order = :order')
            ->andWhere('l.result = :result')
            ->setParameter('order', $order)
            ->setParameter('result', FulfillmentAllocationLog::RESULT_SUCCESS)
            ->orderBy('l.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
