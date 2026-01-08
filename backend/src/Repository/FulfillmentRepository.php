<?php

namespace App\Repository;

use App\Entity\Fulfillment;
use App\Entity\Merchant;
use App\Entity\Order;
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

    /**
     * 商户端分页查询履约单.
     *
     * @return array{data: Fulfillment[], total: int}
     */
    public function findByMerchantPaginated(
        Merchant $merchant,
        int $page = 1,
        int $limit = 20,
        ?string $status = null,
        ?string $fulfillmentType = null
    ): array {
        $qb = $this->createQueryBuilder('f')
            ->where('f.merchant = :merchant')
            ->setParameter('merchant', $merchant);

        if ($status !== null) {
            $qb->andWhere('f.status = :status')
                ->setParameter('status', $status);
        }

        if ($fulfillmentType !== null) {
            $qb->andWhere('f.fulfillmentType = :fulfillmentType')
                ->setParameter('fulfillmentType', $fulfillmentType);
        }

        // 获取总数
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(f.id)')->getQuery()->getSingleScalarResult();

        // 获取分页数据
        $data = $qb
            ->orderBy('f.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['data' => $data, 'total' => $total];
    }

    /**
     * 管理端分页查询履约单.
     *
     * @return array{data: Fulfillment[], total: int}
     */
    public function findPaginated(
        int $page = 1,
        int $limit = 20,
        ?string $status = null,
        ?string $fulfillmentType = null,
        ?string $merchantId = null,
        ?string $orderId = null
    ): array {
        $qb = $this->createQueryBuilder('f');

        if ($status !== null) {
            $qb->andWhere('f.status = :status')
                ->setParameter('status', $status);
        }

        if ($fulfillmentType !== null) {
            $qb->andWhere('f.fulfillmentType = :fulfillmentType')
                ->setParameter('fulfillmentType', $fulfillmentType);
        }

        if ($merchantId !== null) {
            $qb->join('f.merchant', 'm')
                ->andWhere('m.id = :merchantId')
                ->setParameter('merchantId', $merchantId);
        }

        if ($orderId !== null) {
            $qb->join('f.order', 'o')
                ->andWhere('o.id = :orderId')
                ->setParameter('orderId', $orderId);
        }

        // 获取总数
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(f.id)')->getQuery()->getSingleScalarResult();

        // 获取分页数据
        $data = $qb
            ->orderBy('f.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['data' => $data, 'total' => $total];
    }

    /**
     * 查找已过期的自履约订单（待处理且已超过截止时间）.
     *
     * @return Fulfillment[]
     */
    public function findExpiredSelfFulfillments(): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.fulfillmentType = :type')
            ->andWhere('f.status = :status')
            ->andWhere('f.deadlineAt IS NOT NULL')
            ->andWhere('f.deadlineAt < :now')
            ->setParameter('type', Fulfillment::TYPE_MERCHANT_WAREHOUSE)
            ->setParameter('status', Fulfillment::STATUS_PENDING)
            ->setParameter('now', new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->getQuery()
            ->getResult();
    }

    /**
     * 统计商户某状态的履约单数量.
     */
    public function countByMerchantAndStatus(Merchant $merchant, string $status): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.merchant = :merchant')
            ->andWhere('f.status = :status')
            ->setParameter('merchant', $merchant)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * 获取订单的活跃履约单（非取消、非拒绝）.
     *
     * @return Fulfillment[]
     */
    public function findActiveByOrder(Order $order): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.order = :order')
            ->andWhere('f.status NOT IN (:excludedStatuses)')
            ->setParameter('order', $order)
            ->setParameter('excludedStatuses', [
                Fulfillment::STATUS_CANCELLED,
                Fulfillment::STATUS_REJECTED,
                Fulfillment::STATUS_EXPIRED,
            ])
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
