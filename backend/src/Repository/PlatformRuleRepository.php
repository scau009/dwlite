<?php

namespace App\Repository;

use App\Entity\PlatformRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlatformRule>
 */
class PlatformRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatformRule::class);
    }

    public function save(PlatformRule $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PlatformRule $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 查找所有规则.
     *
     * @return PlatformRule[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.type', 'ASC')
            ->addOrderBy('r.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找所有活跃规则.
     *
     * @return PlatformRule[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.isActive = true')
            ->orderBy('r.type', 'ASC')
            ->addOrderBy('r.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 按类型查找规则.
     *
     * @return PlatformRule[]
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.type = :type')
            ->setParameter('type', $type)
            ->orderBy('r.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 按类型查找活跃规则.
     *
     * @return PlatformRule[]
     */
    public function findActiveByType(string $type): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.type = :type')
            ->andWhere('r.isActive = true')
            ->setParameter('type', $type)
            ->orderBy('r.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 按编码查找规则.
     */
    public function findByCode(string $code): ?PlatformRule
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 检查编码是否已存在.
     */
    public function existsByCode(string $code, ?string $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.code = :code')
            ->setParameter('code', $code);

        if ($excludeId) {
            $qb->andWhere('r.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * 分页查询规则.
     *
     * @return array{data: PlatformRule[], total: int}
     */
    public function findPaginated(
        int $page = 1,
        int $limit = 20,
        ?string $type = null,
        ?string $search = null,
        ?bool $isActive = null,
    ): array {
        $qb = $this->createQueryBuilder('r');

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
