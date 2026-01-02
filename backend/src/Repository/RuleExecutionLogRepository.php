<?php

namespace App\Repository;

use App\Entity\RuleExecutionLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RuleExecutionLog>
 */
class RuleExecutionLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RuleExecutionLog::class);
    }

    public function save(RuleExecutionLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 按规则查找执行日志.
     *
     * @return RuleExecutionLog[]
     */
    public function findByRule(string $ruleType, string $ruleId, int $limit = 100): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.ruleType = :ruleType')
            ->andWhere('l.ruleId = :ruleId')
            ->setParameter('ruleType', $ruleType)
            ->setParameter('ruleId', $ruleId)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * 按上下文查找执行日志.
     *
     * @return RuleExecutionLog[]
     */
    public function findByContext(string $contextType, string $contextId, int $limit = 100): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.contextType = :contextType')
            ->andWhere('l.contextId = :contextId')
            ->setParameter('contextType', $contextType)
            ->setParameter('contextId', $contextId)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找失败的执行日志.
     *
     * @return RuleExecutionLog[]
     */
    public function findFailures(int $limit = 100): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.success = false')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * 按规则查找失败的执行日志.
     *
     * @return RuleExecutionLog[]
     */
    public function findFailuresByRule(string $ruleType, string $ruleId, int $limit = 100): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.ruleType = :ruleType')
            ->andWhere('l.ruleId = :ruleId')
            ->andWhere('l.success = false')
            ->setParameter('ruleType', $ruleType)
            ->setParameter('ruleId', $ruleId)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * 清理旧日志.
     */
    public function cleanOldLogs(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('l')
            ->delete()
            ->andWhere('l.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }

    /**
     * 获取规则执行统计.
     *
     * @return array{total: int, success: int, failure: int, avgTimeMs: float}
     */
    public function getStatsByRule(string $ruleType, string $ruleId): array
    {
        $result = $this->createQueryBuilder('l')
            ->select('COUNT(l.id) as total')
            ->addSelect('SUM(CASE WHEN l.success = true THEN 1 ELSE 0 END) as success')
            ->addSelect('SUM(CASE WHEN l.success = false THEN 1 ELSE 0 END) as failure')
            ->addSelect('AVG(l.executionTimeMs) as avgTimeMs')
            ->andWhere('l.ruleType = :ruleType')
            ->andWhere('l.ruleId = :ruleId')
            ->setParameter('ruleType', $ruleType)
            ->setParameter('ruleId', $ruleId)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) $result['total'],
            'success' => (int) $result['success'],
            'failure' => (int) $result['failure'],
            'avgTimeMs' => (float) ($result['avgTimeMs'] ?? 0),
        ];
    }
}
