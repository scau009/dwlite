<?php

namespace App\Repository;

use App\Entity\Warehouse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Warehouse>
 */
class WarehouseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Warehouse::class);
    }

    /**
     * 获取所有正常运营的仓库
     *
     * @return Warehouse[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.status = :status')
            ->setParameter('status', Warehouse::STATUS_ACTIVE)
            ->orderBy('w.sortOrder', 'ASC')
            ->addOrderBy('w.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取所有正常运营的平台仓库（用于入库单选择）
     *
     * @return Warehouse[]
     */
    public function findActivePlatformWarehouses(): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.status = :status')
            ->andWhere('w.category = :category')
            ->setParameter('status', Warehouse::STATUS_ACTIVE)
            ->setParameter('category', Warehouse::CATEGORY_PLATFORM)
            ->orderBy('w.sortOrder', 'ASC')
            ->addOrderBy('w.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 按国家代码查找仓库
     *
     * @return Warehouse[]
     */
    public function findByCountry(string $countryCode): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.countryCode = :countryCode')
            ->andWhere('w.status = :status')
            ->setParameter('countryCode', $countryCode)
            ->setParameter('status', Warehouse::STATUS_ACTIVE)
            ->orderBy('w.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 按类型查找仓库
     *
     * @return Warehouse[]
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.type = :type')
            ->andWhere('w.status = :status')
            ->setParameter('type', $type)
            ->setParameter('status', Warehouse::STATUS_ACTIVE)
            ->orderBy('w.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找支持指定物流公司的仓库
     *
     * @return Warehouse[]
     */
    public function findByCarrier(string $carrier): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.status = :status')
            ->andWhere('JSON_CONTAINS(w.supportedCarriers, :carrier) = 1')
            ->setParameter('status', Warehouse::STATUS_ACTIVE)
            ->setParameter('carrier', json_encode($carrier))
            ->orderBy('w.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找已启用 API 对接的仓库
     *
     * @return Warehouse[]
     */
    public function findWithApiEnabled(): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.apiEnabled = :enabled')
            ->andWhere('w.status = :status')
            ->setParameter('enabled', true)
            ->setParameter('status', Warehouse::STATUS_ACTIVE)
            ->orderBy('w.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找合同即将到期的仓库（30天内）
     *
     * @return Warehouse[]
     */
    public function findContractExpiringWithin(int $days = 30): array
    {
        $now = new \DateTimeImmutable();
        $futureDate = $now->modify("+{$days} days");

        return $this->createQueryBuilder('w')
            ->andWhere('w.contractEndDate IS NOT NULL')
            ->andWhere('w.contractEndDate >= :now')
            ->andWhere('w.contractEndDate <= :futureDate')
            ->andWhere('w.status = :status')
            ->setParameter('now', $now)
            ->setParameter('futureDate', $futureDate)
            ->setParameter('status', Warehouse::STATUS_ACTIVE)
            ->orderBy('w.contractEndDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 分页查询仓库列表
     *
     * @return array{data: Warehouse[], total: int}
     */
    public function findPaginated(int $page = 1, int $limit = 20, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('w');

        // 搜索条件
        if (!empty($filters['name'])) {
            $qb->andWhere('w.name LIKE :name OR w.code LIKE :name')
               ->setParameter('name', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['code'])) {
            $qb->andWhere('w.code LIKE :code')
               ->setParameter('code', '%' . $filters['code'] . '%');
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('w.type = :type')
               ->setParameter('type', $filters['type']);
        }

        if (!empty($filters['category'])) {
            $qb->andWhere('w.category = :category')
               ->setParameter('category', $filters['category']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('w.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['countryCode'])) {
            $qb->andWhere('w.countryCode = :countryCode')
               ->setParameter('countryCode', $filters['countryCode']);
        }

        // 计算总数
        $countQb = clone $qb;
        $total = $countQb->select('COUNT(w.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // 分页和排序
        $data = $qb
            ->orderBy('w.sortOrder', 'ASC')
            ->addOrderBy('w.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'data' => $data,
            'total' => (int) $total,
        ];
    }

    /**
     * 保存仓库
     */
    public function save(Warehouse $warehouse, bool $flush = false): void
    {
        $this->getEntityManager()->persist($warehouse);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除仓库
     */
    public function remove(Warehouse $warehouse, bool $flush = false): void
    {
        $this->getEntityManager()->remove($warehouse);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}