<?php

namespace App\Repository;

use App\Entity\PlatformRule;
use App\Entity\PlatformRuleAssignment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlatformRuleAssignment>
 */
class PlatformRuleAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatformRuleAssignment::class);
    }

    public function save(PlatformRuleAssignment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PlatformRuleAssignment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 查找规则的所有分配.
     *
     * @return PlatformRuleAssignment[]
     */
    public function findByRule(PlatformRule $rule): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.platformRule = :rule')
            ->setParameter('rule', $rule)
            ->orderBy('a.scopeType', 'ASC')
            ->addOrderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 按范围查找规则分配.
     *
     * @return PlatformRuleAssignment[]
     */
    public function findByScope(string $scopeType, string $scopeId): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.platformRule', 'r')
            ->andWhere('a.scopeType = :scopeType')
            ->andWhere('a.scopeId = :scopeId')
            ->setParameter('scopeType', $scopeType)
            ->setParameter('scopeId', $scopeId)
            ->orderBy('r.type', 'ASC')
            ->addOrderBy('a.priorityOverride', 'ASC')
            ->addOrderBy('r.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 按范围查找活跃规则分配.
     *
     * @return PlatformRuleAssignment[]
     */
    public function findActiveByScope(string $scopeType, string $scopeId): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.platformRule', 'r')
            ->andWhere('a.scopeType = :scopeType')
            ->andWhere('a.scopeId = :scopeId')
            ->andWhere('a.isActive = true')
            ->andWhere('r.isActive = true')
            ->setParameter('scopeType', $scopeType)
            ->setParameter('scopeId', $scopeId)
            ->orderBy('r.type', 'ASC')
            ->addOrderBy('a.priorityOverride', 'ASC')
            ->addOrderBy('r.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 按范围和类型查找活跃规则分配.
     *
     * @return PlatformRuleAssignment[]
     */
    public function findActiveByScopeAndType(string $scopeType, string $scopeId, string $ruleType): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.platformRule', 'r')
            ->andWhere('a.scopeType = :scopeType')
            ->andWhere('a.scopeId = :scopeId')
            ->andWhere('a.isActive = true')
            ->andWhere('r.isActive = true')
            ->andWhere('r.type = :ruleType')
            ->setParameter('scopeType', $scopeType)
            ->setParameter('scopeId', $scopeId)
            ->setParameter('ruleType', $ruleType)
            ->orderBy('a.priorityOverride', 'ASC')
            ->addOrderBy('r.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找特定的规则分配.
     */
    public function findOneByRuleAndScope(PlatformRule $rule, string $scopeType, string $scopeId): ?PlatformRuleAssignment
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.platformRule = :rule')
            ->andWhere('a.scopeType = :scopeType')
            ->andWhere('a.scopeId = :scopeId')
            ->setParameter('rule', $rule)
            ->setParameter('scopeType', $scopeType)
            ->setParameter('scopeId', $scopeId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 检查规则是否已分配到范围.
     */
    public function existsByRuleAndScope(PlatformRule $rule, string $scopeType, string $scopeId): bool
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.platformRule = :rule')
            ->andWhere('a.scopeType = :scopeType')
            ->andWhere('a.scopeId = :scopeId')
            ->setParameter('rule', $rule)
            ->setParameter('scopeType', $scopeType)
            ->setParameter('scopeId', $scopeId)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * 查找商户的所有规则分配.
     *
     * @return PlatformRuleAssignment[]
     */
    public function findByMerchant(string $merchantId): array
    {
        return $this->findByScope(PlatformRuleAssignment::SCOPE_MERCHANT, $merchantId);
    }

    /**
     * 查找渠道商品的所有规则分配.
     *
     * @return PlatformRuleAssignment[]
     */
    public function findByChannelProduct(string $channelProductId): array
    {
        return $this->findByScope(PlatformRuleAssignment::SCOPE_CHANNEL_PRODUCT, $channelProductId);
    }
}
