<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Fulfillment;
use App\Entity\Merchant;
use App\Entity\Settlement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Settlement>
 */
class SettlementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Settlement::class);
    }

    /**
     * 查找可执行结算的结算单（状态为 pending 且已过预计时间）.
     *
     * @return Settlement[]
     */
    public function findReadyToSettle(int $limit = 100): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->andWhere('s.scheduledSettleAt <= :now')
            ->setParameter('status', Settlement::STATUS_PENDING)
            ->setParameter('now', $now)
            ->orderBy('s.scheduledSettleAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * 根据履约单查找结算单.
     */
    public function findByFulfillment(Fulfillment $fulfillment): ?Settlement
    {
        return $this->createQueryBuilder('s')
            ->where('s.fulfillment = :fulfillment')
            ->setParameter('fulfillment', $fulfillment)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 获取商户的结算单列表（分页）.
     *
     * @param array<string, mixed> $filters
     *
     * @return array{data: Settlement[], total: int}
     */
    public function findByMerchantPaginated(
        Merchant $merchant,
        int $page = 1,
        int $limit = 20,
        array $filters = [],
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->where('s.merchant = :merchant')
            ->setParameter('merchant', $merchant);

        if (!empty($filters['settlementNo'])) {
            $qb->andWhere('s.settlementNo LIKE :settlementNo')
                ->setParameter('settlementNo', '%'.$filters['settlementNo'].'%');
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('s.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['scheduledSettleAtFrom'])) {
            $qb->andWhere('s.scheduledSettleAt >= :from')
                ->setParameter('from', $filters['scheduledSettleAtFrom']);
        }

        if (!empty($filters['scheduledSettleAtTo'])) {
            $qb->andWhere('s.scheduledSettleAt <= :to')
                ->setParameter('to', $filters['scheduledSettleAtTo']);
        }

        // 获取总数
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // 获取数据
        $data = $qb->orderBy('s.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['data' => $data, 'total' => $total];
    }

    /**
     * 统计商户的结算金额.
     *
     * @return array{pending: string, settled: string}
     */
    public function sumByMerchant(Merchant $merchant): array
    {
        $result = $this->createQueryBuilder('s')
            ->select('s.status, SUM(s.netAmount) as total')
            ->where('s.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->groupBy('s.status')
            ->getQuery()
            ->getResult();

        $sums = ['pending' => '0.00', 'settled' => '0.00'];
        foreach ($result as $row) {
            if ($row['status'] === Settlement::STATUS_PENDING) {
                $sums['pending'] = $row['total'] ?? '0.00';
            } elseif ($row['status'] === Settlement::STATUS_SETTLED) {
                $sums['settled'] = $row['total'] ?? '0.00';
            }
        }

        return $sums;
    }

    /**
     * 统计商户各状态的结算单数量.
     *
     * @return array<string, int>
     */
    public function countByMerchantGroupByStatus(Merchant $merchant): array
    {
        $result = $this->createQueryBuilder('s')
            ->select('s.status, COUNT(s.id) as cnt')
            ->where('s.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->groupBy('s.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * 管理员分页查询结算单.
     *
     * @param array<string, mixed> $filters
     *
     * @return array{data: Settlement[], total: int}
     */
    public function findPaginated(int $page, int $limit, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.merchant', 'm')
            ->leftJoin('s.order', 'o')
            ->leftJoin('s.fulfillment', 'f');

        if (!empty($filters['settlementNo'])) {
            $qb->andWhere('s.settlementNo LIKE :settlementNo')
                ->setParameter('settlementNo', '%'.$filters['settlementNo'].'%');
        }

        if (!empty($filters['merchantId'])) {
            $qb->andWhere('s.merchant = :merchant')
                ->setParameter('merchant', $filters['merchantId']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('s.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['scheduledSettleAtFrom'])) {
            $qb->andWhere('s.scheduledSettleAt >= :from')
                ->setParameter('from', $filters['scheduledSettleAtFrom']);
        }

        if (!empty($filters['scheduledSettleAtTo'])) {
            $qb->andWhere('s.scheduledSettleAt <= :to')
                ->setParameter('to', $filters['scheduledSettleAtTo']);
        }

        // 获取总数
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // 获取数据
        $data = $qb->orderBy('s.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['data' => $data, 'total' => $total];
    }
}
