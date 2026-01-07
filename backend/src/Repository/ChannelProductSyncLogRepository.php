<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ChannelProductSyncLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChannelProductSyncLog>
 */
class ChannelProductSyncLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChannelProductSyncLog::class);
    }

    /**
     * Find logs by channel product ID.
     *
     * @return ChannelProductSyncLog[]
     */
    public function findByChannelProduct(string $channelProductId, int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.channelProductId = :channelProductId')
            ->setParameter('channelProductId', $channelProductId)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find logs by sales channel.
     *
     * @return ChannelProductSyncLog[]
     */
    public function findBySalesChannel(string $salesChannelId, int $limit = 100): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.salesChannelId = :salesChannelId')
            ->setParameter('salesChannelId', $salesChannelId)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find failed logs for retry.
     *
     * @return ChannelProductSyncLog[]
     */
    public function findFailed(?string $salesChannelId = null, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.status = :status')
            ->setParameter('status', ChannelProductSyncLog::STATUS_FAILED)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($salesChannelId !== null) {
            $qb->andWhere('l.salesChannelId = :salesChannelId')
                ->setParameter('salesChannelId', $salesChannelId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find logs by merchant.
     *
     * @return ChannelProductSyncLog[]
     */
    public function findByMerchant(string $merchantId, int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.triggerMerchantId = :merchantId')
            ->setParameter('merchantId', $merchantId)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count logs by status for a sales channel.
     *
     * @return array<string, int>
     */
    public function countByStatusForChannel(string $salesChannelId): array
    {
        $result = $this->createQueryBuilder('l')
            ->select('l.status, COUNT(l.id) as count')
            ->where('l.salesChannelId = :salesChannelId')
            ->setParameter('salesChannelId', $salesChannelId)
            ->groupBy('l.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Get average duration by operation type.
     *
     * @return array<string, float>
     */
    public function getAverageDurationByOperation(string $salesChannelId, \DateTimeImmutable $since): array
    {
        $result = $this->createQueryBuilder('l')
            ->select('l.operation, AVG(l.durationMs) as avgDuration')
            ->where('l.salesChannelId = :salesChannelId')
            ->andWhere('l.createdAt >= :since')
            ->andWhere('l.status = :status')
            ->andWhere('l.durationMs IS NOT NULL')
            ->setParameter('salesChannelId', $salesChannelId)
            ->setParameter('since', $since)
            ->setParameter('status', ChannelProductSyncLog::STATUS_SUCCESS)
            ->groupBy('l.operation')
            ->getQuery()
            ->getResult();

        $durations = [];
        foreach ($result as $row) {
            $durations[$row['operation']] = (float) $row['avgDuration'];
        }

        return $durations;
    }

    /**
     * Delete old logs for cleanup.
     */
    public function deleteOlderThan(\DateTimeImmutable $threshold): int
    {
        return $this->createQueryBuilder('l')
            ->delete()
            ->where('l.createdAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }

    /**
     * Find recent logs with pagination.
     *
     * @return array{logs: ChannelProductSyncLog[], total: int}
     */
    public function findRecentPaginated(
        int $page = 1,
        int $limit = 20,
        ?string $salesChannelId = null,
        ?string $status = null,
        ?string $operation = null,
    ): array {
        $qb = $this->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC');

        if ($salesChannelId !== null) {
            $qb->andWhere('l.salesChannelId = :salesChannelId')
                ->setParameter('salesChannelId', $salesChannelId);
        }

        if ($status !== null) {
            $qb->andWhere('l.status = :status')
                ->setParameter('status', $status);
        }

        if ($operation !== null) {
            $qb->andWhere('l.operation = :operation')
                ->setParameter('operation', $operation);
        }

        // Count total
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(l.id)')->getQuery()->getSingleScalarResult();

        // Get paginated results
        $logs = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'logs' => $logs,
            'total' => $total,
        ];
    }

    /**
     * Find logs by channel product with pagination.
     *
     * @return array{data: ChannelProductSyncLog[], total: int}
     */
    public function findByChannelProductPaginated(
        string $channelProductId,
        int $page = 1,
        int $limit = 20,
        ?string $status = null,
        ?string $operation = null,
    ): array {
        $qb = $this->createQueryBuilder('l')
            ->where('l.channelProductId = :channelProductId')
            ->setParameter('channelProductId', $channelProductId)
            ->orderBy('l.createdAt', 'DESC');

        if ($status !== null) {
            $qb->andWhere('l.status = :status')
                ->setParameter('status', $status);
        }

        if ($operation !== null) {
            $qb->andWhere('l.operation = :operation')
                ->setParameter('operation', $operation);
        }

        // Count total
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(l.id)')->getQuery()->getSingleScalarResult();

        // Get paginated results
        $logs = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'data' => $logs,
            'total' => $total,
        ];
    }
}
