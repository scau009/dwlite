<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\SalesChannel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function findByExternalOrderId(string $externalOrderId, SalesChannel $channel): ?Order
    {
        return $this->findOneBy([
            'externalOrderId' => $externalOrderId,
            'salesChannel' => $channel,
        ]);
    }

    /**
     * @return Order[]
     */
    public function findPendingAllocation(): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.status = :status')
            ->andWhere('o.paymentStatus = :paymentStatus')
            ->setParameter('status', Order::STATUS_PENDING)
            ->setParameter('paymentStatus', Order::PAYMENT_PAID)
            ->orderBy('o.placedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Order[]
     */
    public function findByStatus(string $status, int $limit = 100): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.status = :status')
            ->setParameter('status', $status)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}