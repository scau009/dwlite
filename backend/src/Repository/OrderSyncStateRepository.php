<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderSyncState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderSyncState>
 */
class OrderSyncStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderSyncState::class);
    }

    /**
     * 获取或创建订单的同步状态.
     */
    public function getOrCreate(Order $order): OrderSyncState
    {
        $state = $this->findOneBy(['order' => $order]);

        if ($state === null) {
            $state = new OrderSyncState($order);
            /** @var EntityManagerInterface $em */
            $em = $this->getEntityManager();
            $em->persist($state);
        }

        return $state;
    }

    /**
     * 查找有待执行操作且需要重试的同步状态.
     *
     * @return OrderSyncState[]
     */
    public function findPendingOperations(
        \DateTimeImmutable $threshold,
        int $maxRetryCount = 5,
        int $limit = 100,
    ): array {
        return $this->createQueryBuilder('s')
            ->where('s.pendingOperation IS NOT NULL')
            ->andWhere('s.retryCount < :maxRetry')
            ->andWhere('s.lastAttemptAt IS NULL OR s.lastAttemptAt < :threshold')
            ->setParameter('maxRetry', $maxRetryCount)
            ->setParameter('threshold', $threshold)
            ->orderBy('s.lastAttemptAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找未确认的已支付订单（用于补偿扫描）.
     *
     * @return OrderSyncState[]
     */
    public function findUnconfirmedPaidOrders(int $limit = 100): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.order', 'o')
            ->where('s.isConfirmed = :false')
            ->andWhere('o.paymentStatus = :paid')
            ->andWhere('o.status != :cancelled')
            ->setParameter('false', false)
            ->setParameter('paid', Order::PAYMENT_PAID)
            ->setParameter('cancelled', Order::STATUS_CANCELLED)
            ->orderBy('o.paidAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找已发货但未同步发货信息的订单.
     *
     * @return OrderSyncState[]
     */
    public function findUnshippedOrders(int $limit = 100): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.order', 'o')
            ->where('s.shippedSyncCount = :zero')
            ->andWhere('o.status IN (:shippedStatuses)')
            ->setParameter('zero', 0)
            ->setParameter('shippedStatuses', [
                Order::STATUS_SHIPPED,
                Order::STATUS_DELIVERED,
                Order::STATUS_COMPLETED,
            ])
            ->orderBy('o.shippedAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * 根据订单ID查找同步状态.
     */
    public function findByOrderId(string $orderId): ?OrderSyncState
    {
        return $this->createQueryBuilder('s')
            ->join('s.order', 'o')
            ->where('o.id = :orderId')
            ->setParameter('orderId', $orderId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
