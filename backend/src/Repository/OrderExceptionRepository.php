<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderException>
 */
class OrderExceptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderException::class);
    }

    /**
     * 统计订单的待处理异常数量.
     */
    public function countPendingByOrder(Order $order): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.order = :order')
            ->andWhere('e.status = :status')
            ->setParameter('order', $order)
            ->setParameter('status', OrderException::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * 查找订单的所有异常.
     *
     * @return OrderException[]
     */
    public function findByOrder(Order $order): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.order = :order')
            ->setParameter('order', $order)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找订单的待处理异常.
     *
     * @return OrderException[]
     */
    public function findPendingByOrder(Order $order): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.order = :order')
            ->andWhere('e.status = :status')
            ->setParameter('order', $order)
            ->setParameter('status', OrderException::STATUS_PENDING)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 分页查询异常列表.
     *
     * @return array{items: OrderException[], total: int}
     */
    public function findPaginated(
        int $page = 1,
        int $pageSize = 20,
        ?string $status = null,
        ?string $type = null,
        ?string $orderId = null,
        ?string $salesChannelId = null,
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.order', 'o')
            ->addSelect('o');

        if ($status !== null) {
            $qb->andWhere('e.status = :status')
                ->setParameter('status', $status);
        }

        if ($type !== null) {
            $qb->andWhere('e.type = :type')
                ->setParameter('type', $type);
        }

        if ($orderId !== null) {
            $qb->andWhere('o.id = :orderId')
                ->setParameter('orderId', $orderId);
        }

        if ($salesChannelId !== null) {
            $qb->andWhere('o.salesChannel = :salesChannelId')
                ->setParameter('salesChannelId', $salesChannelId);
        }

        // 获取总数
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // 获取分页数据
        $items = $qb->orderBy('e.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * 统计各状态的异常数量.
     *
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('e')
            ->select('e.status, COUNT(e.id) as cnt')
            ->groupBy('e.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * 统计各类型的异常数量.
     *
     * @return array<string, int>
     */
    public function countByType(): array
    {
        $result = $this->createQueryBuilder('e')
            ->select('e.type, COUNT(e.id) as cnt')
            ->groupBy('e.type')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['type']] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * 查找指定时间范围内创建的异常.
     *
     * @return OrderException[]
     */
    public function findByDateRange(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        ?string $status = null,
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->where('e.createdAt >= :startDate')
            ->andWhere('e.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        if ($status !== null) {
            $qb->andWhere('e.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 统计所有待处理异常数量.
     */
    public function countAllPending(): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.status = :status')
            ->setParameter('status', OrderException::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * 获取最近的异常.
     *
     * @return OrderException[]
     */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.order', 'o')
            ->addSelect('o')
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
