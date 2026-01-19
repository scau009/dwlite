<?php

namespace App\Repository;

use App\Entity\ApiKey;
use App\Entity\Merchant;
use App\Entity\Warehouse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiKey>
 */
class ApiKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiKey::class);
    }

    public function save(ApiKey $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ApiKey $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find an API Key by its public key ID.
     */
    public function findByKeyId(string $keyId): ?ApiKey
    {
        return $this->findOneBy(['keyId' => $keyId]);
    }

    /**
     * Find active API Key by key ID.
     */
    public function findActiveByKeyId(string $keyId): ?ApiKey
    {
        return $this->findOneBy([
            'keyId' => $keyId,
            'status' => ApiKey::STATUS_ACTIVE,
        ]);
    }

    /**
     * Find all API Keys for a merchant.
     *
     * @return ApiKey[]
     */
    public function findByMerchant(Merchant $merchant): array
    {
        return $this->findBy(
            ['merchant' => $merchant],
            ['createdAt' => 'DESC']
        );
    }

    /**
     * Find all API Keys for a warehouse.
     *
     * @return ApiKey[]
     */
    public function findByWarehouse(Warehouse $warehouse): array
    {
        return $this->findBy(
            ['warehouse' => $warehouse],
            ['createdAt' => 'DESC']
        );
    }

    /**
     * Find all API Keys by type.
     *
     * @return ApiKey[]
     */
    public function findByType(string $type): array
    {
        return $this->findBy(
            ['type' => $type],
            ['createdAt' => 'DESC']
        );
    }

    /**
     * Paginated query for API Keys.
     *
     * @param int $page Page number (starting from 1)
     * @param int $limit Items per page
     * @param array{type?: string, status?: string, merchantId?: string, warehouseId?: string} $filters
     *
     * @return array{data: ApiKey[], total: int}
     */
    public function findPaginated(int $page = 1, int $limit = 20, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.warehouse', 'w')
            ->leftJoin('a.merchant', 'm')
            ->addSelect('w', 'm')
            ->orderBy('a.createdAt', 'DESC');

        if (!empty($filters['type'])) {
            $qb->andWhere('a.type = :type')
                ->setParameter('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['merchantId'])) {
            $qb->andWhere('m.id = :merchantId')
                ->setParameter('merchantId', $filters['merchantId']);
        }

        if (!empty($filters['warehouseId'])) {
            $qb->andWhere('w.id = :warehouseId')
                ->setParameter('warehouseId', $filters['warehouseId']);
        }

        // Get total count
        $countQb = clone $qb;
        $total = $countQb->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Paginate
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
     * Count active API Keys by merchant.
     */
    public function countActiveByMerchant(Merchant $merchant): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.merchant = :merchant')
            ->andWhere('a.status = :status')
            ->setParameter('merchant', $merchant)
            ->setParameter('status', ApiKey::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count active API Keys by warehouse.
     */
    public function countActiveByWarehouse(Warehouse $warehouse): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.warehouse = :warehouse')
            ->andWhere('a.status = :status')
            ->setParameter('warehouse', $warehouse)
            ->setParameter('status', ApiKey::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
