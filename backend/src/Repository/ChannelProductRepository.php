<?php

namespace App\Repository;

use App\Entity\ChannelProduct;
use App\Entity\ProductSku;
use App\Entity\SalesChannel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChannelProduct>
 */
class ChannelProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChannelProduct::class);
    }

    /**
     * 获取某渠道的所有商品
     *
     * @return ChannelProduct[]
     */
    public function findByChannel(SalesChannel $channel): array
    {
        return $this->createQueryBuilder('cp')
            ->andWhere('cp.salesChannel = :channel')
            ->setParameter('channel', $channel)
            ->orderBy('cp.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取某渠道已上架的商品
     *
     * @return ChannelProduct[]
     */
    public function findActiveByChannel(SalesChannel $channel): array
    {
        return $this->createQueryBuilder('cp')
            ->andWhere('cp.salesChannel = :channel')
            ->andWhere('cp.status = :status')
            ->setParameter('channel', $channel)
            ->setParameter('status', ChannelProduct::STATUS_ACTIVE)
            ->orderBy('cp.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取某 SKU 在指定渠道的商品
     */
    public function findOneByChannelAndSku(SalesChannel $channel, ProductSku $sku): ?ChannelProduct
    {
        return $this->findOneBy([
            'salesChannel' => $channel,
            'productSku' => $sku,
        ]);
    }

    /**
     * 获取或创建渠道商品
     */
    public function findOrCreate(SalesChannel $channel, ProductSku $sku): ChannelProduct
    {
        $product = $this->findOneByChannelAndSku($channel, $sku);

        if ($product === null) {
            $product = new ChannelProduct();
            $product->setSalesChannel($channel);
            $product->setProductSku($sku);
        }

        return $product;
    }

    /**
     * 获取需要同步的商品
     *
     * @return ChannelProduct[]
     */
    public function findNeedsSync(?SalesChannel $channel = null, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('cp')
            ->andWhere('cp.syncStatus = :status')
            ->andWhere('cp.status = :activeStatus')
            ->setParameter('status', ChannelProduct::SYNC_STATUS_PENDING)
            ->setParameter('activeStatus', ChannelProduct::STATUS_ACTIVE)
            ->setMaxResults($limit);

        if ($channel !== null) {
            $qb->andWhere('cp.salesChannel = :channel')
                ->setParameter('channel', $channel);
        }

        return $qb->orderBy('cp.updatedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取同步失败的商品
     *
     * @return ChannelProduct[]
     */
    public function findSyncFailed(?SalesChannel $channel = null): array
    {
        $qb = $this->createQueryBuilder('cp')
            ->andWhere('cp.syncStatus = :status')
            ->setParameter('status', ChannelProduct::SYNC_STATUS_FAILED);

        if ($channel !== null) {
            $qb->andWhere('cp.salesChannel = :channel')
                ->setParameter('channel', $channel);
        }

        return $qb->orderBy('cp.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 通过外部 ID 查找.
     */
    public function findByExternalId(SalesChannel $channel, string $externalId): ?ChannelProduct
    {
        return $this->findOneBy([
            'salesChannel' => $channel,
            'externalId' => $externalId,
        ]);
    }

    /**
     * 统计各状态的商品数量.
     */
    public function countByChannelGroupByStatus(SalesChannel $channel): array
    {
        $results = $this->createQueryBuilder('cp')
            ->select('cp.status, COUNT(cp.id) as count')
            ->andWhere('cp.salesChannel = :channel')
            ->setParameter('channel', $channel)
            ->groupBy('cp.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * 获取库存为零的商品
     *
     * @return ChannelProduct[]
     */
    public function findOutOfStock(SalesChannel $channel): array
    {
        return $this->createQueryBuilder('cp')
            ->andWhere('cp.salesChannel = :channel')
            ->andWhere('cp.stockQuantity = 0')
            ->andWhere('cp.status = :status')
            ->setParameter('channel', $channel)
            ->setParameter('status', ChannelProduct::STATUS_ACTIVE)
            ->getQuery()
            ->getResult();
    }

    /**
     * 批量更新同步状态
     */
    public function markAllAsNeedsSync(SalesChannel $channel): int
    {
        return $this->createQueryBuilder('cp')
            ->update()
            ->set('cp.syncStatus', ':status')
            ->andWhere('cp.salesChannel = :channel')
            ->andWhere('cp.status = :activeStatus')
            ->setParameter('status', ChannelProduct::SYNC_STATUS_PENDING)
            ->setParameter('channel', $channel)
            ->setParameter('activeStatus', ChannelProduct::STATUS_ACTIVE)
            ->getQuery()
            ->execute();
    }
}
