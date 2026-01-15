<?php

namespace App\Repository;

use App\Entity\ApiKey;
use App\Entity\ApiKeyLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiKeyLog>
 */
class ApiKeyLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiKeyLog::class);
    }

    public function save(ApiKeyLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find logs by API Key with pagination.
     *
     * @param int $page Page number (starting from 1)
     * @param int $limit Items per page
     *
     * @return array{data: ApiKeyLog[], total: int}
     */
    public function findByApiKeyPaginated(ApiKey $apiKey, int $page = 1, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.apiKey = :apiKey')
            ->setParameter('apiKey', $apiKey)
            ->orderBy('l.createdAt', 'DESC');

        $countQb = clone $qb;
        $total = $countQb->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $offset = ($page - 1) * $limit;
        $data = $qb->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'data' => $data,
            'total' => (int) $total,
        ];
    }

    /**
     * Find logs by request ID.
     */
    public function findByRequestId(string $requestId): ?ApiKeyLog
    {
        return $this->findOneBy(['requestId' => $requestId]);
    }

    /**
     * Get API usage statistics for an API Key.
     *
     * @return array{totalRequests: int, successRequests: int, errorRequests: int, avgResponseTime: float}
     */
    public function getStatsByApiKey(ApiKey $apiKey, ?\DateTimeImmutable $since = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->select(
                'COUNT(l.id) as totalRequests',
                'SUM(CASE WHEN l.statusCode >= 200 AND l.statusCode < 300 THEN 1 ELSE 0 END) as successRequests',
                'SUM(CASE WHEN l.statusCode >= 400 THEN 1 ELSE 0 END) as errorRequests',
                'AVG(l.responseTimeMs) as avgResponseTime'
            )
            ->where('l.apiKey = :apiKey')
            ->setParameter('apiKey', $apiKey);

        if ($since !== null) {
            $qb->andWhere('l.createdAt >= :since')
                ->setParameter('since', $since);
        }

        $result = $qb->getQuery()->getSingleResult();

        return [
            'totalRequests' => (int) ($result['totalRequests'] ?? 0),
            'successRequests' => (int) ($result['successRequests'] ?? 0),
            'errorRequests' => (int) ($result['errorRequests'] ?? 0),
            'avgResponseTime' => (float) ($result['avgResponseTime'] ?? 0),
        ];
    }

    /**
     * Get hourly request counts for an API Key (last 24 hours).
     *
     * @return array<array{hour: string, count: int}>
     */
    public function getHourlyStatsByApiKey(ApiKey $apiKey): array
    {
        $since = new \DateTimeImmutable('-24 hours', new \DateTimeZone('UTC'));

        return $this->createQueryBuilder('l')
            ->select("DATE_FORMAT(l.createdAt, '%Y-%m-%d %H:00:00') as hour, COUNT(l.id) as count")
            ->where('l.apiKey = :apiKey')
            ->andWhere('l.createdAt >= :since')
            ->setParameter('apiKey', $apiKey)
            ->setParameter('since', $since)
            ->groupBy('hour')
            ->orderBy('hour', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Delete old logs (for cleanup).
     *
     * @param int $daysToKeep Number of days to keep logs
     *
     * @return int Number of deleted records
     */
    public function deleteOldLogs(int $daysToKeep = 30): int
    {
        $threshold = new \DateTimeImmutable(
            sprintf('-%d days', $daysToKeep),
            new \DateTimeZone('UTC')
        );

        return $this->createQueryBuilder('l')
            ->delete()
            ->where('l.createdAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }
}
