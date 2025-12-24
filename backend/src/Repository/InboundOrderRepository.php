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
    public function findByMerchant(
        Merchant $merchant,
        ?string $status = null,
        ?string $trackingNumber = null,
        int $limit = 50
    ): array {
        $qb = $this->createQueryBuilder('io')
            ->andWhere('io.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->orderBy('io.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($status !== null) {
            $qb->andWhere('io.status = :status')
                ->setParameter('status', $status);
        }

        if ($trackingNumber !== null) {
            $qb->leftJoin('io.shipment', 's')
                ->andWhere('s.trackingNumber LIKE :trackingNumber')
                ->setParameter('trackingNumber', '%' . $trackingNumber . '%');
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

    /**
     * 按仓库分页查询入库单
     *
     * @return array{data: InboundOrder[], meta: array{total: int, page: int, limit: int, pages: int}}
     */
    public function findByWarehousePaginated(
        Warehouse $warehouse,
        int $page = 1,
        int $limit = 20,
        array $filters = []
    ): array {
        $qb = $this->createQueryBuilder('io')
            ->andWhere('io.warehouse = :warehouse')
            ->setParameter('warehouse', $warehouse)
            ->orderBy('io.createdAt', 'DESC');

        // 应用筛选条件
        if (!empty($filters['status'])) {
            $qb->andWhere('io.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['orderNo'])) {
            $qb->andWhere('io.orderNo LIKE :orderNo')
                ->setParameter('orderNo', '%' . $filters['orderNo'] . '%');
        }

        // 计算总数
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(io.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // 分页
        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $data = $qb->getQuery()->getResult();

        return [
            'data' => $data,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => (int) ceil($total / $limit),
            ],
        ];
    }

    /**
     * 统计仓库各状态的入库单数量
     */
    public function countByWarehouseGroupByStatus(Warehouse $warehouse): array
    {
        $results = $this->createQueryBuilder('io')
            ->select('io.status, COUNT(io.id) as count')
            ->andWhere('io.warehouse = :warehouse')
            ->setParameter('warehouse', $warehouse)
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
