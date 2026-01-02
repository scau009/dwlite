<?php

namespace App\Repository;

use App\Entity\Merchant;
use App\Entity\MerchantRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MerchantRule>
 */
class MerchantRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MerchantRule::class);
    }

    public function save(MerchantRule $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(MerchantRule $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 查找商户的所有规则.
     *
     * @return MerchantRule[]
     */
    public function findByMerchant(Merchant $merchant): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->orderBy('r.type', 'ASC')
            ->addOrderBy('r.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找商户的活跃规则.
     *
     * @return MerchantRule[]
     */
    public function findActiveByMerchant(Merchant $merchant): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.merchant = :merchant')
            ->andWhere('r.isActive = true')
            ->setParameter('merchant', $merchant)
            ->orderBy('r.type', 'ASC')
            ->addOrderBy('r.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 按类型查找商户的规则.
     *
     * @return MerchantRule[]
     */
    public function findByMerchantAndType(Merchant $merchant, string $type): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.merchant = :merchant')
            ->andWhere('r.type = :type')
            ->setParameter('merchant', $merchant)
            ->setParameter('type', $type)
            ->orderBy('r.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 按编码查找规则.
     */
    public function findByMerchantAndCode(Merchant $merchant, string $code): ?MerchantRule
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.merchant = :merchant')
            ->andWhere('r.code = :code')
            ->setParameter('merchant', $merchant)
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 检查编码是否已存在.
     */
    public function existsByMerchantAndCode(Merchant $merchant, string $code, ?string $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.merchant = :merchant')
            ->andWhere('r.code = :code')
            ->setParameter('merchant', $merchant)
            ->setParameter('code', $code);

        if ($excludeId) {
            $qb->andWhere('r.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * 分页查询商户规则.
     *
     * @return array{data: MerchantRule[], total: int}
     */
    public function findByMerchantPaginated(
        Merchant $merchant,
        int $page = 1,
        int $limit = 20,
        ?string $type = null,
        ?string $search = null,
        ?bool $isActive = null,
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.merchant = :merchant')
            ->setParameter('merchant', $merchant);

        if ($type) {
            $qb->andWhere('r.type = :type')
                ->setParameter('type', $type);
        }

        if ($search) {
            $qb->andWhere('(r.code LIKE :search OR r.name LIKE :search)')
                ->setParameter('search', '%'.$search.'%');
        }

        if (null !== $isActive) {
            $qb->andWhere('r.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

        // 获取总数
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();

        // 获取分页数据
        $data = $qb
            ->orderBy('r.type', 'ASC')
            ->addOrderBy('r.priority', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['data' => $data, 'total' => $total];
    }
}
