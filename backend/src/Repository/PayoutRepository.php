<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Merchant;
use App\Entity\Payout;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payout>
 */
class PayoutRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payout::class);
    }

    /**
     * 获取商户的提现列表（分页）.
     *
     * @param array<string, mixed> $filters
     *
     * @return array{data: Payout[], total: int}
     */
    public function findByMerchantPaginated(
        Merchant $merchant,
        int $page = 1,
        int $limit = 20,
        array $filters = [],
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->where('p.merchant = :merchant')
            ->setParameter('merchant', $merchant);

        if (!empty($filters['payoutNo'])) {
            $qb->andWhere('p.payoutNo LIKE :payoutNo')
                ->setParameter('payoutNo', '%'.$filters['payoutNo'].'%');
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['createdAtFrom'])) {
            $qb->andWhere('p.createdAt >= :from')
                ->setParameter('from', $filters['createdAtFrom']);
        }

        if (!empty($filters['createdAtTo'])) {
            $qb->andWhere('p.createdAt <= :to')
                ->setParameter('to', $filters['createdAtTo']);
        }

        // 获取总数
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // 获取数据
        $data = $qb->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['data' => $data, 'total' => $total];
    }

    /**
     * 获取待审核的提现列表（管理后台用）.
     *
     * @return array{data: Payout[], total: int}
     */
    public function findPendingPaginated(int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', Payout::STATUS_PENDING);

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $data = $qb->orderBy('p.createdAt', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['data' => $data, 'total' => $total];
    }

    /**
     * 获取处理中的提现列表.
     *
     * @return Payout[]
     */
    public function findProcessing(int $limit = 100): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', Payout::STATUS_PROCESSING)
            ->orderBy('p.processingAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * 统计商户各状态的提现单数量.
     *
     * @return array<string, int>
     */
    public function countByMerchantGroupByStatus(Merchant $merchant): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('p.status, COUNT(p.id) as cnt')
            ->where('p.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->groupBy('p.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * 统计商户的提现金额.
     *
     * @return array{pending: string, processing: string, completed: string}
     */
    public function sumByMerchant(Merchant $merchant): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('p.status, SUM(p.amount) as total')
            ->where('p.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->groupBy('p.status')
            ->getQuery()
            ->getResult();

        $sums = [
            'pending' => '0.00',
            'processing' => '0.00',
            'completed' => '0.00',
        ];

        foreach ($result as $row) {
            if (isset($sums[$row['status']])) {
                $sums[$row['status']] = $row['total'] ?? '0.00';
            }
        }

        return $sums;
    }

    /**
     * 管理员分页查询提现单.
     *
     * @param array<string, mixed> $filters
     *
     * @return array{data: Payout[], total: int}
     */
    public function findPaginated(int $page, int $limit, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.merchant', 'm');

        if (!empty($filters['payoutNo'])) {
            $qb->andWhere('p.payoutNo LIKE :payoutNo')
                ->setParameter('payoutNo', '%'.$filters['payoutNo'].'%');
        }

        if (!empty($filters['merchantId'])) {
            $qb->andWhere('p.merchant = :merchant')
                ->setParameter('merchant', $filters['merchantId']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['createdAtFrom'])) {
            $qb->andWhere('p.createdAt >= :from')
                ->setParameter('from', $filters['createdAtFrom']);
        }

        if (!empty($filters['createdAtTo'])) {
            $qb->andWhere('p.createdAt <= :to')
                ->setParameter('to', $filters['createdAtTo']);
        }

        // 获取总数
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // 获取数据
        $data = $qb->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['data' => $data, 'total' => $total];
    }

    public function save(Payout $payout, bool $flush = false): void
    {
        $this->getEntityManager()->persist($payout);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
