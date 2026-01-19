<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\SalesChannel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function findByExternalOrderId(string $externalOrderId, SalesChannel $channel): ?Order
    {
        return $this->findOneBy([
            'externalOrderId' => $externalOrderId,
            'salesChannel' => $channel,
        ]);
    }

    /**
     * @return Order[]
     */
    public function findPendingAllocation(): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.status = :status')
            ->andWhere('o.paymentStatus = :paymentStatus')
            ->setParameter('status', Order::STATUS_PENDING)
            ->setParameter('paymentStatus', Order::PAYMENT_PAID)
            ->orderBy('o.placedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Order[]
     */
    public function findByStatus(string $status, int $limit = 100): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.status = :status')
            ->setParameter('status', $status)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * 分页查询订单列表.
     *
     * @param array<string, mixed> $filters
     *
     * @return array{items: Order[], total: int}
     */
    public function findPaginated(int $page = 1, int $pageSize = 20, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.salesChannel', 'sc')
            ->addSelect('sc');

        // 销售渠道筛选
        if (!empty($filters['salesChannelId'])) {
            $qb->andWhere('o.salesChannel = :salesChannelId')
                ->setParameter('salesChannelId', $filters['salesChannelId']);
        }

        // 订单状态筛选
        if (!empty($filters['status'])) {
            $qb->andWhere('o.status = :status')
                ->setParameter('status', $filters['status']);
        }

        // 支付状态筛选
        if (!empty($filters['paymentStatus'])) {
            $qb->andWhere('o.paymentStatus = :paymentStatus')
                ->setParameter('paymentStatus', $filters['paymentStatus']);
        }

        // 搜索（订单号或外部订单号）
        if (!empty($filters['search'])) {
            $qb->andWhere('o.orderNo LIKE :search OR o.externalOrderNo LIKE :search')
                ->setParameter('search', '%'.$filters['search'].'%');
        }

        // 日期范围筛选
        if (!empty($filters['startDate'])) {
            $startDate = new \DateTimeImmutable($filters['startDate'], new \DateTimeZone('UTC'));
            $qb->andWhere('o.placedAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if (!empty($filters['endDate'])) {
            $endDate = new \DateTimeImmutable($filters['endDate'], new \DateTimeZone('UTC'));
            // 结束日期加一天，以包含整天
            $endDate = $endDate->modify('+1 day');
            $qb->andWhere('o.placedAt < :endDate')
                ->setParameter('endDate', $endDate);
        }

        // 获取总数
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // 获取分页数据
        $items = $qb->orderBy('o.placedAt', 'DESC')
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
     * 统计各状态的订单数量.
     *
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('o')
            ->select('o.status, COUNT(o.id) as cnt')
            ->groupBy('o.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * 统计各支付状态的订单数量.
     *
     * @return array<string, int>
     */
    public function countByPaymentStatus(): array
    {
        $result = $this->createQueryBuilder('o')
            ->select('o.paymentStatus, COUNT(o.id) as cnt')
            ->groupBy('o.paymentStatus')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['paymentStatus']] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * 统计各渠道的订单数量.
     *
     * @return array<string, int>
     */
    public function countBySalesChannel(): array
    {
        $result = $this->createQueryBuilder('o')
            ->select('IDENTITY(o.salesChannel) as channelId, COUNT(o.id) as cnt')
            ->groupBy('o.salesChannel')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['channelId']] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * 统计今日订单数量.
     */
    public function countToday(): int
    {
        $timezone = new \DateTimeZone('Asia/Shanghai');
        $today = new \DateTimeImmutable('today', $timezone);
        $tomorrow = $today->modify('+1 day');

        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.placedAt >= :start')
            ->andWhere('o.placedAt < :end')
            ->setParameter('start', $today->setTimezone(new \DateTimeZone('UTC')))
            ->setParameter('end', $tomorrow->setTimezone(new \DateTimeZone('UTC')))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * 统计今日订单总金额.
     */
    public function sumTodayRevenue(): string
    {
        $timezone = new \DateTimeZone('Asia/Shanghai');
        $today = new \DateTimeImmutable('today', $timezone);
        $tomorrow = $today->modify('+1 day');

        $result = $this->createQueryBuilder('o')
            ->select('SUM(o.totalAmount)')
            ->where('o.placedAt >= :start')
            ->andWhere('o.placedAt < :end')
            ->setParameter('start', $today->setTimezone(new \DateTimeZone('UTC')))
            ->setParameter('end', $tomorrow->setTimezone(new \DateTimeZone('UTC')))
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (string) $result : '0.00';
    }

    /**
     * 统计昨日订单数量.
     */
    public function countYesterday(): int
    {
        $timezone = new \DateTimeZone('Asia/Shanghai');
        $yesterday = new \DateTimeImmutable('yesterday', $timezone);
        $today = new \DateTimeImmutable('today', $timezone);

        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.placedAt >= :start')
            ->andWhere('o.placedAt < :end')
            ->setParameter('start', $yesterday->setTimezone(new \DateTimeZone('UTC')))
            ->setParameter('end', $today->setTimezone(new \DateTimeZone('UTC')))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * 统计昨日订单总金额.
     */
    public function sumYesterdayRevenue(): string
    {
        $timezone = new \DateTimeZone('Asia/Shanghai');
        $yesterday = new \DateTimeImmutable('yesterday', $timezone);
        $today = new \DateTimeImmutable('today', $timezone);

        $result = $this->createQueryBuilder('o')
            ->select('SUM(o.totalAmount)')
            ->where('o.placedAt >= :start')
            ->andWhere('o.placedAt < :end')
            ->setParameter('start', $yesterday->setTimezone(new \DateTimeZone('UTC')))
            ->setParameter('end', $today->setTimezone(new \DateTimeZone('UTC')))
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (string) $result : '0.00';
    }

    /**
     * 按日期范围统计订单数量.
     *
     * @return array<string, int> 日期 => 数量
     */
    public function countByDateRange(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "SELECT DATE(CONVERT_TZ(placed_at, '+00:00', '+08:00')) as order_date, COUNT(id) as cnt
                FROM orders
                WHERE placed_at >= :start AND placed_at < :end
                GROUP BY order_date";

        $result = $conn->executeQuery($sql, [
            'start' => $startDate->format('Y-m-d H:i:s'),
            'end' => $endDate->format('Y-m-d H:i:s'),
        ])->fetchAllAssociative();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['order_date']] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * 获取最近的订单.
     *
     * @return Order[]
     */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.salesChannel', 'sc')
            ->addSelect('sc')
            ->orderBy('o.placedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
