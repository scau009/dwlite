<?php

namespace App\Repository;

use App\Entity\SalesChannel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SalesChannel>
 */
class SalesChannelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SalesChannel::class);
    }

    public function save(SalesChannel $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SalesChannel $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByCode(string $code): ?SalesChannel
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', SalesChannel::STATUS_ACTIVE)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAvailable(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status IN (:statuses)')
            ->setParameter('statuses', [SalesChannel::STATUS_ACTIVE, SalesChannel::STATUS_MAINTENANCE])
            ->orderBy('c.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{data: SalesChannel[], total: int}
     */
    public function findPaginated(int $page = 1, int $limit = 20, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.createdAt', 'DESC');

        if (!empty($filters['name'])) {
            $qb->andWhere('c.name LIKE :name OR c.code LIKE :name')
                ->setParameter('name', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['code'])) {
            $qb->andWhere('c.code LIKE :code')
                ->setParameter('code', '%' . $filters['code'] . '%');
        }

        if (!empty($filters['businessType'])) {
            $qb->andWhere('c.businessType = :businessType')
                ->setParameter('businessType', $filters['businessType']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $filters['status']);
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();

        $offset = ($page - 1) * $limit;
        $data = $qb->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();

        return ['data' => $data, 'total' => $total];
    }

    public function existsByCode(string $code, ?string $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.code = :code')
            ->setParameter('code', $code);

        if ($excludeId !== null) {
            $qb->andWhere('c.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
