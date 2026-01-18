<?php

namespace App\Repository;

use App\Entity\ChannelProduct;
use App\Entity\Fulfillment;
use App\Entity\FulfillmentItem;
use App\Entity\InventoryReservation;
use App\Entity\MerchantInventory;
use App\Entity\Order;
use App\Entity\OrderItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryReservation>
 */
class InventoryReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryReservation::class);
    }

    /**
     * 根据订单项查找活跃预留.
     */
    public function findActiveByOrderItem(OrderItem $orderItem): ?InventoryReservation
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.orderItem = :orderItem')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('orderItem', $orderItem)
            ->setParameter('statuses', [
                InventoryReservation::STATUS_RESERVED,
                InventoryReservation::STATUS_ALLOCATED,
                InventoryReservation::STATUS_LOCKED,
            ])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 根据订单查找所有活跃预留.
     *
     * @return InventoryReservation[]
     */
    public function findActiveByOrder(Order $order): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.order = :order')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('order', $order)
            ->setParameter('statuses', [
                InventoryReservation::STATUS_RESERVED,
                InventoryReservation::STATUS_ALLOCATED,
                InventoryReservation::STATUS_LOCKED,
            ])
            ->getQuery()
            ->getResult();
    }

    /**
     * 根据履约单查找预留.
     *
     * @return InventoryReservation[]
     */
    public function findByFulfillment(Fulfillment $fulfillment): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.fulfillment = :fulfillment')
            ->setParameter('fulfillment', $fulfillment)
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找过期的预留（用于清理任务）.
     *
     * @return InventoryReservation[]
     */
    public function findExpired(\DateTimeImmutable $now, int $limit = 100): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.expiresAt < :now')
            ->setParameter('statuses', [
                InventoryReservation::STATUS_RESERVED,
                InventoryReservation::STATUS_ALLOCATED,
            ])
            ->setParameter('now', $now)
            ->orderBy('r.expiresAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取渠道商品的已预留数量.
     */
    public function getReservedQuantityByChannelProduct(ChannelProduct $channelProduct): int
    {
        $result = $this->createQueryBuilder('r')
            ->select('SUM(r.quantity)')
            ->andWhere('r.channelProduct = :channelProduct')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('channelProduct', $channelProduct)
            ->setParameter('statuses', [
                InventoryReservation::STATUS_RESERVED,
                InventoryReservation::STATUS_ALLOCATED,
                InventoryReservation::STATUS_LOCKED,
            ])
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * 获取库存的待确认预留数量（软锁定）.
     */
    public function getPendingReserveQuantityByInventory(MerchantInventory $inventory): int
    {
        $result = $this->createQueryBuilder('r')
            ->select('SUM(r.quantity)')
            ->andWhere('r.inventory = :inventory')
            ->andWhere('r.status = :status')
            ->setParameter('inventory', $inventory)
            ->setParameter('status', InventoryReservation::STATUS_ALLOCATED)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * 获取库存的已锁定预留数量（硬锁定）.
     */
    public function getLockedQuantityByInventory(MerchantInventory $inventory): int
    {
        $result = $this->createQueryBuilder('r')
            ->select('SUM(r.quantity)')
            ->andWhere('r.inventory = :inventory')
            ->andWhere('r.status = :status')
            ->setParameter('inventory', $inventory)
            ->setParameter('status', InventoryReservation::STATUS_LOCKED)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * 检查订单项是否已有预留.
     */
    public function hasReservationForOrderItem(OrderItem $orderItem): bool
    {
        $result = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.orderItem = :orderItem')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('orderItem', $orderItem)
            ->setParameter('statuses', [
                InventoryReservation::STATUS_RESERVED,
                InventoryReservation::STATUS_ALLOCATED,
                InventoryReservation::STATUS_LOCKED,
            ])
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result > 0;
    }

    /**
     * 获取预留统计（用于监控）.
     *
     * @return array{reserved: int, allocated: int, locked: int, total: int}
     */
    public function getStatsByChannelProduct(ChannelProduct $channelProduct): array
    {
        $results = $this->createQueryBuilder('r')
            ->select('r.status, SUM(r.quantity) as quantity')
            ->andWhere('r.channelProduct = :channelProduct')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('channelProduct', $channelProduct)
            ->setParameter('statuses', [
                InventoryReservation::STATUS_RESERVED,
                InventoryReservation::STATUS_ALLOCATED,
                InventoryReservation::STATUS_LOCKED,
            ])
            ->groupBy('r.status')
            ->getQuery()
            ->getResult();

        $stats = [
            'reserved' => 0,
            'allocated' => 0,
            'locked' => 0,
            'total' => 0,
        ];

        foreach ($results as $row) {
            $status = $row['status'];
            $qty = (int) $row['quantity'];
            $stats[$status] = $qty;
            $stats['total'] += $qty;
        }

        return $stats;
    }

    /**
     * 根据履约项查找预留.
     */
    public function findByFulfillmentItem(FulfillmentItem $fulfillmentItem): ?InventoryReservation
    {
        return $this->findOneBy(['fulfillmentItem' => $fulfillmentItem]);
    }

    /**
     * 查找待分配的预留（状态为 reserved）.
     *
     * @return InventoryReservation[]
     */
    public function findPendingAllocation(int $limit = 100): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->setParameter('status', InventoryReservation::STATUS_RESERVED)
            ->orderBy('r.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * 批量删除已完成的预留记录（清理用）.
     */
    public function deleteCompletedOlderThan(\DateTimeImmutable $threshold): int
    {
        return $this->createQueryBuilder('r')
            ->delete()
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.updatedAt < :threshold')
            ->setParameter('statuses', [
                InventoryReservation::STATUS_COMPLETED,
                InventoryReservation::STATUS_RELEASED,
                InventoryReservation::STATUS_EXPIRED,
            ])
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }
}
