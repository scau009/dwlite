<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function save(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findBySlug(string $slug): ?Product
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function findByStyleNumber(string $styleNumber): ?Product
    {
        return $this->findOneBy(['styleNumber' => $styleNumber]);
    }

    public function createFilterQueryBuilder(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.brand', 'b')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.skus', 's');

        if (!empty($filters['search'])) {
            $qb->andWhere('p.name LIKE :search OR p.styleNumber LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['brandId'])) {
            $qb->andWhere('p.brand = :brandId')
                ->setParameter('brandId', $filters['brandId']);
        }

        if (!empty($filters['categoryId'])) {
            $qb->andWhere('p.category = :categoryId')
                ->setParameter('categoryId', $filters['categoryId']);
        }

        if (!empty($filters['season'])) {
            $qb->andWhere('p.season = :season')
                ->setParameter('season', $filters['season']);
        }

        if (!empty($filters['styleNumber'])) {
            $qb->andWhere('p.styleNumber = :styleNumber')
                ->setParameter('styleNumber', $filters['styleNumber']);
        }

        if (isset($filters['isActive'])) {
            $qb->andWhere('p.isActive = :isActive')
                ->setParameter('isActive', $filters['isActive']);
        }

        if (!empty($filters['tagIds'])) {
            $qb->innerJoin('p.tags', 't')
                ->andWhere('t.id IN (:tagIds)')
                ->setParameter('tagIds', $filters['tagIds']);
        }

        if (!empty($filters['minPrice'])) {
            $qb->andWhere('s.price >= :minPrice')
                ->setParameter('minPrice', $filters['minPrice']);
        }

        if (!empty($filters['maxPrice'])) {
            $qb->andWhere('s.price <= :maxPrice')
                ->setParameter('maxPrice', $filters['maxPrice']);
        }

        $sortBy = $filters['sortBy'] ?? 'createdAt';
        $sortOrder = strtoupper($filters['sortOrder'] ?? 'DESC');

        $sortFieldMap = [
            'created_at' => 'p.createdAt',
            'updated_at' => 'p.updatedAt',
            'name' => 'p.name',
            'style_number' => 'p.styleNumber',
            'season' => 'p.season',
        ];

        $sortField = $sortFieldMap[$sortBy] ?? 'p.createdAt';
        $qb->orderBy($sortField, $sortOrder);

        return $qb;
    }

    public function findWithFilters(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $qb = $this->createFilterQueryBuilder($filters);

        $total = (clone $qb)
            ->select('COUNT(DISTINCT p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $results = $qb
            ->select('p')
            ->distinct()
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
}
