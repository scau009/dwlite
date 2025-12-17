<?php

namespace App\Repository;

use App\Entity\InboundOrder;
use App\Entity\Merchant;
use App\Entity\Warehouse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InboundOrder>
 */
class InboundOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InboundOrder::class);
    }

    /**
     * 获取商户的送仓单列表
     *
     * @return InboundOrder[]
     */
    public function findByMerchant(Merchant $merchant, ?string $status = null, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('io')
            ->andWhere('io.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->orderBy('io.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($status !== null) {
            $qb->andWhere('io.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 获取仓库的待处理入库单
     *
     * @return InboundOrder[]
     */
    public function findPendingByWarehouse(Warehouse $warehouse): array
    {
        return $this->createQueryBuilder('io')
            ->andWhere('io.warehouse = :warehouse')
            ->andWhere('io.status IN (:statuses)')
            ->setParameter('warehouse', $warehouse)
            ->setParameter('statuses', [
                InboundOrder::STATUS_SHIPPED,
                InboundOrder::STATUS_ARRIVED,
                InboundOrder::STATUS_RECEIVING,
            ])
            ->orderBy('io.shippedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 按单号查找
     */
    public function findByOrderNo(string $orderNo): ?InboundOrder
    {
        return $this->findOneBy(['orderNo' => $orderNo]);
    }

    /**
     * 获取商户的草稿单
     *
     * @return InboundOrder[]
     */
    public function findDraftsByMerchant(Merchant $merchant): array
    {
        return $this->createQueryBuilder('io')
            ->andWhere('io.merchant = :merchant')
            ->andWhere('io.status = :status')
            ->setParameter('merchant', $merchant)
            ->setParameter('status', InboundOrder::STATUS_DRAFT)
            ->orderBy('io.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 统计商户各状态的送仓单数量
     */
    public function countByMerchantGroupByStatus(Merchant $merchant): array
    {
        $results = $this->createQueryBuilder('io')
            ->select('io.status, COUNT(io.id) as count')
            ->andWhere('io.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->groupBy('io.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }
        return $counts;
    }
}
