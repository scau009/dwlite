<?php

namespace App\Repository;

use App\Entity\ListingOperationLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ListingOperationLog>
 */
class ListingOperationLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ListingOperationLog::class);
    }

    /**
     * 根据上架ID查询操作日志.
     *
     * @return ListingOperationLog[]
     */
    public function findByListing(string $listingId, int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.listingId = :listingId')
            ->setParameter('listingId', $listingId)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * 按商户分页查询操作日志.
     *
     * @return array{data: ListingOperationLog[], total: int}
     */
    public function findByMerchantPaginated(
        string $merchantId,
        int $page = 1,
        int $limit = 20,
        array $filters = []
    ): array {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.merchantId = :merchantId')
            ->setParameter('merchantId', $merchantId)
            ->orderBy('l.createdAt', 'DESC');

        // 按操作类型筛选
        if (!empty($filters['operation'])) {
            $qb->andWhere('l.operation = :operation')
                ->setParameter('operation', $filters['operation']);
        }

        // 按上架ID筛选
        if (!empty($filters['listingId'])) {
            $qb->andWhere('l.listingId = :listingId')
                ->setParameter('listingId', $filters['listingId']);
        }

        // 按时间范围筛选
        if (!empty($filters['startDate'])) {
            $qb->andWhere('l.createdAt >= :startDate')
                ->setParameter('startDate', new \DateTimeImmutable($filters['startDate'], new \DateTimeZone('UTC')));
        }

        if (!empty($filters['endDate'])) {
            $endDate = new \DateTimeImmutable($filters['endDate'], new \DateTimeZone('UTC'));
            $endDate = $endDate->modify('+1 day');
            $qb->andWhere('l.createdAt < :endDate')
                ->setParameter('endDate', $endDate);
        }

        // 计算总数
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // 分页
        $data = $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'data' => $data,
            'total' => $total,
        ];
    }
}
