<?php

namespace App\Repository;

use App\Entity\Fulfillment;
use App\Entity\Merchant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Fulfillment>
 */
class FulfillmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fulfillment::class);
    }

    /**
     * 获取商家待处理的履约单（商家仓发货）.
     *
     * @return Fulfillment[]
     */
    public function findPendingForMerchant(Merchant $merchant): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.merchant = :merchant')
            ->andWhere('f.fulfillmentType = :type')
            ->andWhere('f.status IN (:statuses)')
            ->setParameter('merchant', $merchant)
            ->setParameter('type', Fulfillment::TYPE_MERCHANT_WAREHOUSE)
            ->setParameter('statuses', [Fulfillment::STATUS_PENDING, Fulfillment::STATUS_PROCESSING])
            ->orderBy('f.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Fulfillment[]
     */
    public function findByStatus(string $status, int $limit = 100): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.status = :status')
            ->setParameter('status', $status)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
