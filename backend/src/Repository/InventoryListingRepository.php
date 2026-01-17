<?php

namespace App\Repository;

use App\Entity\InventoryListing;
use App\Entity\Merchant;
use App\Entity\MerchantInventory;
use App\Entity\MerchantSalesChannel;
use App\Entity\SalesChannel;
use App\Service\BusinessNoGenerator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryListing>
 */
class InventoryListingRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private BusinessNoGenerator $businessNoGenerator,
    ) {
        parent::__construct($registry, InventoryListing::class);
    }

    /**
     * 获取商户的所有上架配置.
     *
     * @return InventoryListing[]
     */
    public function findByMerchant(Merchant $merchant): array
    {
        return $this->createQueryBuilder('l')
            ->join('l.merchantInventory', 'i')
            ->andWhere('i.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->orderBy('l.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取某库存的所有上架配置.
     *
     * @return InventoryListing[]
     */
    public function findByInventory(MerchantInventory $inventory): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.merchantInventory = :inventory')
            ->setParameter('inventory', $inventory)
            ->orderBy('l.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取某渠道的所有上架配置.
     *
     * @return InventoryListing[]
     */
    public function findByChannel(MerchantSalesChannel $channel): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.merchantSalesChannel = :channel')
            ->setParameter('channel', $channel)
            ->orderBy('l.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取商户在某渠道的所有上架配置.
     *
     * @return InventoryListing[]
     */
    public function findByMerchantAndChannel(Merchant $merchant, SalesChannel $channel): array
    {
        return $this->createQueryBuilder('l')
            ->join('l.merchantInventory', 'i')
            ->join('l.merchantSalesChannel', 'mc')
            ->join('mc.salesChannel', 'c')
            ->andWhere('i.merchant = :merchant')
            ->andWhere('c = :channel')
            ->setParameter('merchant', $merchant)
            ->setParameter('channel', $channel)
            ->orderBy('l.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取已激活的上架配置.
     *
     * @return InventoryListing[]
     */
    public function findActiveByMerchant(Merchant $merchant): array
    {
        return $this->createQueryBuilder('l')
            ->join('l.merchantInventory', 'i')
            ->andWhere('i.merchant = :merchant')
            ->andWhere('l.status = :status')
            ->setParameter('merchant', $merchant)
            ->setParameter('status', InventoryListing::STATUS_ACTIVE)
            ->orderBy('l.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取某库存在指定渠道的上架配置.
     */
    public function findOneByInventoryAndChannel(
        MerchantInventory $inventory,
        MerchantSalesChannel $channel
    ): ?InventoryListing {
        return $this->findOneBy([
            'merchantInventory' => $inventory,
            'merchantSalesChannel' => $channel,
        ]);
    }

    /**
     * 获取或创建上架配置.
     */
    public function findOrCreate(
        MerchantInventory $inventory,
        MerchantSalesChannel $channel
    ): InventoryListing {
        $listing = $this->findOneByInventoryAndChannel($inventory, $channel);

        if ($listing === null) {
            $listing = new InventoryListing();
            $listing->setId($this->businessNoGenerator->generateInventoryListingId());
            $listing->setMerchantInventory($inventory);
            $listing->setMerchantSalesChannel($channel);
        }

        return $listing;
    }

    /**
     * 统计商户各状态的上架数量.
     */
    public function countByMerchantGroupByStatus(Merchant $merchant): array
    {
        $results = $this->createQueryBuilder('l')
            ->select('l.status, COUNT(l.id) as count')
            ->join('l.merchantInventory', 'i')
            ->andWhere('i.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->groupBy('l.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * 获取使用独占模式的上架配置.
     *
     * @return InventoryListing[]
     */
    public function findDedicatedByInventory(MerchantInventory $inventory): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.merchantInventory = :inventory')
            ->andWhere('l.allocationMode = :mode')
            ->setParameter('inventory', $inventory)
            ->setParameter('mode', InventoryListing::MODE_DEDICATED)
            ->getQuery()
            ->getResult();
    }

    /**
     * 计算库存已分配的总量.
     */
    public function sumAllocatedByInventory(MerchantInventory $inventory): int
    {
        $result = $this->createQueryBuilder('l')
            ->select('SUM(l.allocatedQuantity) as total')
            ->andWhere('l.merchantInventory = :inventory')
            ->andWhere('l.allocationMode = :mode')
            ->setParameter('inventory', $inventory)
            ->setParameter('mode', InventoryListing::MODE_DEDICATED)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * 按商户分页查询上架记录.
     *
     * @return array{data: InventoryListing[], total: int}
     */
    public function findByMerchantPaginated(
        Merchant $merchant,
        int $page = 1,
        int $limit = 20,
        array $filters = []
    ): array {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.merchantInventory', 'i')
            ->leftJoin('i.productSku', 'sku')
            ->leftJoin('sku.product', 'p')
            ->leftJoin('l.merchantSalesChannel', 'mc')
            ->leftJoin('mc.salesChannel', 'sc')
            ->andWhere('i.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->orderBy('l.updatedAt', 'DESC');

        // 搜索商品名或货号
        if (!empty($filters['search'])) {
            $qb->andWhere('p.name LIKE :search OR p.styleNumber LIKE :search OR sku.sizeValue LIKE :search')
                ->setParameter('search', '%'.$filters['search'].'%');
        }

        // 按渠道筛选
        if (!empty($filters['channelId'])) {
            $qb->andWhere('mc.id = :channelId')
                ->setParameter('channelId', $filters['channelId']);
        }

        // 按状态筛选
        if (!empty($filters['status'])) {
            $qb->andWhere('l.status = :status')
                ->setParameter('status', $filters['status']);
        }

        // 按履约模式筛选
        if (!empty($filters['fulfillmentType'])) {
            $qb->andWhere('l.fulfillmentType = :fulfillmentType')
                ->setParameter('fulfillmentType', $filters['fulfillmentType']);
        }

        // 按定价模式筛选
        if (!empty($filters['pricingModel'])) {
            $qb->andWhere('l.pricingModel = :pricingModel')
                ->setParameter('pricingModel', $filters['pricingModel']);
        }

        // 计算总数
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // 分页
        $data = $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'data' => $data,
            'total' => $total,
        ];
    }
}
