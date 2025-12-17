<?php

namespace App\Repository;

use App\Entity\InboundShipment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InboundShipment>
 */
class InboundShipmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InboundShipment::class);
    }

    /**
     * 按运单号查找
     */
    public function findByTrackingNumber(string $trackingNumber): ?InboundShipment
    {
        return $this->findOneBy(['trackingNumber' => $trackingNumber]);
    }

    /**
     * 获取在途的物流单
     *
     * @return InboundShipment[]
     */
    public function findInTransit(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :status')
            ->setParameter('status', InboundShipment::STATUS_IN_TRANSIT)
            ->orderBy('s.shippedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
