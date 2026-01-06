<?php

namespace App\Repository;

use App\Entity\MerchantRule;
use App\Entity\MerchantRuleAssignment;
use App\Entity\MerchantSalesChannel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MerchantRuleAssignment>
 */
class MerchantRuleAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MerchantRuleAssignment::class);
    }

    public function save(MerchantRuleAssignment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(MerchantRuleAssignment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 查找规则的所有分配.
     *
     * @return MerchantRuleAssignment[]
     */
    public function findByRule(MerchantRule $rule): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.merchantRule = :rule')
            ->setParameter('rule', $rule)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找渠道配置的所有规则分配.
     *
     * @return MerchantRuleAssignment[]
     */
    public function findByMerchantSalesChannel(MerchantSalesChannel $channel): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.merchantRule', 'r')
            ->andWhere('a.merchantSalesChannel = :channel')
            ->setParameter('channel', $channel)
            ->orderBy('r.type', 'ASC')
            ->addOrderBy('a.priorityOverride', 'ASC')
            ->addOrderBy('r.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找渠道配置的活跃规则分配.
     *
     * @return MerchantRuleAssignment[]
     */
    public function findActiveByMerchantSalesChannel(MerchantSalesChannel $channel): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.merchantRule', 'r')
            ->andWhere('a.merchantSalesChannel = :channel')
            ->andWhere('a.isActive = true')
            ->andWhere('r.isActive = true')
            ->setParameter('channel', $channel)
            ->orderBy('r.type', 'ASC')
            ->addOrderBy('a.priorityOverride', 'ASC')
            ->addOrderBy('r.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 按类型查找渠道配置的活跃规则分配.
     *
     * @return MerchantRuleAssignment[]
     */
    public function findActiveByMerchantSalesChannelAndType(MerchantSalesChannel $channel, string $type): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.merchantRule', 'r')
            ->andWhere('a.merchantSalesChannel = :channel')
            ->andWhere('a.isActive = true')
            ->andWhere('r.isActive = true')
            ->andWhere('r.type = :type')
            ->setParameter('channel', $channel)
            ->setParameter('type', $type)
            ->orderBy('a.priorityOverride', 'ASC')
            ->addOrderBy('r.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找特定的规则分配.
     */
    public function findOneByRuleAndChannel(MerchantRule $rule, MerchantSalesChannel $channel): ?MerchantRuleAssignment
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.merchantRule = :rule')
            ->andWhere('a.merchantSalesChannel = :channel')
            ->setParameter('rule', $rule)
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 检查规则是否已分配到渠道.
     */
    public function existsByRuleAndChannel(MerchantRule $rule, MerchantSalesChannel $channel): bool
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.merchantRule = :rule')
            ->andWhere('a.merchantSalesChannel = :channel')
            ->setParameter('rule', $rule)
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
