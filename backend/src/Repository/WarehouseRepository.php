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
}