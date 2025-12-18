<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function save(Category $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Category $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findBySlug(string $slug): ?Category
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function findRootCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.parent IS NULL')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveTree(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByParent(?Category $parent): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.parent = :parent')
            ->andWhere('c.isActive = :active')
            ->setParameter('parent', $parent)
            ->setParameter('active', true)
            ->orderBy('c.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 分页查询分类列表
     *
     * @param int $page 页码（从1开始）
     * @param int $limit 每页数量
     * @param array $filters 筛选条件 ['name' => string, 'isActive' => bool, 'parentId' => string|null]
     * @return array ['data' => Category[], 'total' => int]
     */
    public function findPaginated(int $page = 1, int $limit = 20, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.createdAt', 'DESC');

        // 名称筛选（模糊匹配）
        if (!empty($filters['name'])) {
            $qb->andWhere('c.name LIKE :name OR c.slug LIKE :name')
                ->setParameter('name', '%' . $filters['name'] . '%');
        }

        // 状态筛选
        if (isset($filters['isActive'])) {
            $qb->andWhere('c.isActive = :isActive')
                ->setParameter('isActive', $filters['isActive']);
        }

        // 父级分类筛选
        if (array_key_exists('parentId', $filters)) {
            if ($filters['parentId'] === null) {
                $qb->andWhere('c.parent IS NULL');
            } else {
                $qb->andWhere('c.parent = :parentId')
                    ->setParameter('parentId', $filters['parentId']);
            }
        }

        // 获取总数
        $countQb = clone $qb;
        $total = $countQb->select('COUNT(c.id)')
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
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId !== null) {
            $qb->andWhere('c.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * 获取所有分类（包括子分类），用于构建树结构
     */
    public function findAllForTree(bool $activeOnly = false): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.parent', 'p')
            ->addSelect('p')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('c.isActive = :active')
                ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }
}
