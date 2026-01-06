<?php

namespace App\Repository;

use App\Entity\ProductSyncJob;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductSyncJob>
 */
class ProductSyncJobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductSyncJob::class);
    }

    public function save(ProductSyncJob $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find the latest sync job for a provider.
     */
    public function findLatestByProvider(string $provider): ?ProductSyncJob
    {
        return $this->createQueryBuilder('j')
            ->where('j.provider = :provider')
            ->setParameter('provider', $provider)
            ->orderBy('j.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find a running sync job for a provider.
     */
    public function findRunningByProvider(string $provider): ?ProductSyncJob
    {
        return $this->findOneBy([
            'provider' => $provider,
            'status' => ProductSyncJob::STATUS_RUNNING,
        ]);
    }

    /**
     * Find sync jobs with pagination.
     *
     * @return array{data: ProductSyncJob[], meta: array}
     */
    public function findPaginated(int $page = 1, int $limit = 20, ?string $provider = null): array
    {
        $qb = $this->createQueryBuilder('j')
            ->orderBy('j.createdAt', 'DESC');

        if ($provider !== null) {
            $qb->where('j.provider = :provider')
                ->setParameter('provider', $provider);
        }

        $total = (clone $qb)
            ->select('COUNT(j.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $results = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'data' => $results,
            'meta' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => (int) $total,
                'total_pages' => (int) ceil($total / $limit),
            ],
        ];
    }

    /**
     * Count jobs by status for a provider.
     *
     * @return array<string, int>
     */
    public function countByStatusForProvider(string $provider): array
    {
        $results = $this->createQueryBuilder('j')
            ->select('j.status, COUNT(j.id) as count')
            ->where('j.provider = :provider')
            ->setParameter('provider', $provider)
            ->groupBy('j.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Clean up old completed/failed jobs.
     *
     * @param int $daysToKeep Number of days to keep completed/failed jobs
     */
    public function cleanupOldJobs(int $daysToKeep = 30): int
    {
        $cutoff = new \DateTimeImmutable("-{$daysToKeep} days", new \DateTimeZone('UTC'));

        return $this->createQueryBuilder('j')
            ->delete()
            ->where('j.status IN (:statuses)')
            ->andWhere('j.createdAt < :cutoff')
            ->setParameter('statuses', [ProductSyncJob::STATUS_COMPLETED, ProductSyncJob::STATUS_FAILED])
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}
