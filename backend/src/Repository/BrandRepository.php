<?php

namespace App\Repository;

use App\Entity\Brand;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Brand>
 */
class BrandRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Brand::class);
    }

    public function save(Brand $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Brand $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findBySlug(string $slug): ?Brand
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('b.sortOrder', 'ASC')
            ->addOrderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 分页查询品牌列表
     *
     * @param int $page 页码（从1开始）
     * @param int $limit 每页数量
     * @param array $filters 筛选条件 ['name' => string, 'isActive' => bool]
     * @return array ['data' => Brand[], 'total' => int]
     */
    public function findPaginated(int $page = 1, int $limit = 20, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('b')
            ->orderBy('b.sortOrder', 'ASC')
            ->addOrderBy('b.createdAt', 'DESC');

        // 名称筛选（模糊匹配）
        if (!empty($filters['name'])) {
            $qb->andWhere('b.name LIKE :name OR b.slug LIKE :name')
                ->setParameter('name', '%' . $filters['name'] . '%');
        }

        // 状态筛选
        if (isset($filters['isActive'])) {
            $qb->andWhere('b.isActive = :isActive')
                ->setParameter('isActive', $filters['isActive']);
        }

        // 获取总数
        $countQb = clone $qb;
        $total = $countQb->select('COUNT(b.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // 分页
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
     * 检查 slug 是否已存在
     */
    public function existsBySlug(string $slug, ?string $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId !== null) {
            $qb->andWhere('b.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
