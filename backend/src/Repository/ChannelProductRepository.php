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

    /**
     * Paginated list with filters for admin.
     *
     * @param array<string, mixed> $filters
     *
     * @return array{data: ChannelProduct[], total: int}
     */
    public function findPaginated(int $page, int $limit, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('cp')
            ->leftJoin('cp.salesChannel', 'sc')
            ->leftJoin('cp.productSku', 'ps')
            ->leftJoin('ps.product', 'p')
            ->leftJoin('p.images', 'pi')
            ->addSelect('sc', 'ps', 'p', 'pi');

        // Filter by sales channel
        if (!empty($filters['salesChannelId'])) {
            $qb->andWhere('sc.id = :salesChannelId')
                ->setParameter('salesChannelId', $filters['salesChannelId']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $qb->andWhere('cp.status = :status')
                ->setParameter('status', $filters['status']);
        }

        // Filter by sync status
        if (!empty($filters['syncStatus'])) {
            $qb->andWhere('cp.syncStatus = :syncStatus')
                ->setParameter('syncStatus', $filters['syncStatus']);
        }

        // Search by style number, size value, or product name
        if (!empty($filters['search'])) {
            $qb->andWhere('(p.styleNumber LIKE :search OR ps.sizeValue LIKE :search OR p.name LIKE :search)')
                ->setParameter('search', '%'.$filters['search'].'%');
        }

        // Get total count
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(cp.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Get paginated data
        $data = $qb->orderBy('cp.updatedAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'data' => $data,
            'total' => $total,
        ];
    }

    /**
     * Find stale pending products for compensation mechanism.
     *
     * Returns products that have been in pending sync status for longer than threshold,
     * indicating they may have been missed by the normal sync process.
     *
     * @return ChannelProduct[]
     */
    public function findStalePending(
        \DateTimeImmutable $threshold,
        ?string $salesChannelId = null,
        int $limit = 100,
    ): array {
        $qb = $this->createQueryBuilder('cp')
            ->andWhere('cp.syncStatus = :syncStatus')
            ->andWhere('cp.status = :activeStatus')
            ->andWhere('cp.updatedAt < :threshold')
            ->setParameter('syncStatus', ChannelProduct::SYNC_STATUS_PENDING)
            ->setParameter('activeStatus', ChannelProduct::STATUS_ACTIVE)
            ->setParameter('threshold', $threshold)
            ->setMaxResults($limit)
            ->orderBy('cp.updatedAt', 'ASC');

        if ($salesChannelId !== null) {
            $qb->andWhere('cp.salesChannel = :salesChannelId')
                ->setParameter('salesChannelId', $salesChannelId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 通过款号和尺码查找渠道商品.
     *
     * @param string $sizeSystem 尺码系统 (US, EU, UK)
     * @param string $sizeValue 尺码值
     */
    public function findByStyleNumberAndSize(
        SalesChannel $channel,
        string $styleNumber,
        string $sizeSystem,
        string $sizeValue,
    ): ?ChannelProduct {
        return $this->createQueryBuilder('cp')
            ->join('cp.productSku', 'ps')
            ->join('ps.product', 'p')
            ->andWhere('cp.salesChannel = :channel')
            ->andWhere('p.styleNumber = :styleNumber')
            ->andWhere('ps.sizeUnit = :sizeUnit')
            ->andWhere('ps.sizeValue = :sizeValue')
            ->setParameter('channel', $channel)
            ->setParameter('styleNumber', $styleNumber)
            ->setParameter('sizeUnit', $sizeSystem)
            ->setParameter('sizeValue', $sizeValue)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 获取渠道商品统计摘要.
     *
     * @return array{total: int, syncFailed: int, outOfStock: int}
     */
    public function getSummaryStats(): array
    {
        // 总数
        $total = (int) $this->createQueryBuilder('cp')
            ->select('COUNT(cp.id)')
            ->where('cp.status = :activeStatus')
            ->setParameter('activeStatus', ChannelProduct::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();

        // 同步失败数
        $syncFailed = (int) $this->createQueryBuilder('cp')
            ->select('COUNT(cp.id)')
            ->where('cp.syncStatus = :failedStatus')
            ->setParameter('failedStatus', ChannelProduct::SYNC_STATUS_FAILED)
            ->getQuery()
            ->getSingleScalarResult();

        // 库存为零数
        $outOfStock = (int) $this->createQueryBuilder('cp')
            ->select('COUNT(cp.id)')
            ->where('cp.status = :activeStatus')
            ->andWhere('cp.stockQuantity = 0')
            ->setParameter('activeStatus', ChannelProduct::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'syncFailed' => $syncFailed,
            'outOfStock' => $outOfStock,
        ];
    }
}
