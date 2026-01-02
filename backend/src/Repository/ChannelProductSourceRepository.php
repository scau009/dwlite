<?php

namespace App\Repository;

use App\Entity\ChannelProduct;
use App\Entity\ChannelProductSource;
use App\Entity\InventoryListing;
use App\Entity\Merchant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChannelProductSource>
 */
class ChannelProductSourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChannelProductSource::class);
    }

    /**
     * 获取渠道商品的所有来源.
     *
     * @return ChannelProductSource[]
     */
    public function findByProduct(ChannelProduct $product): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.channelProduct = :product')
            ->setParameter('product', $product)
            ->orderBy('s.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取渠道商品的活跃来源.
     *
     * @return ChannelProductSource[]
     */
    public function findActiveByProduct(ChannelProduct $product): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.channelProduct = :product')
            ->andWhere('s.isActive = true')
            ->setParameter('product', $product)
            ->orderBy('s.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取某商家上架配置关联的所有渠道商品来源.
     *
     * @return ChannelProductSource[]
     */
    public function findByListing(InventoryListing $listing): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.inventoryListing = :listing')
            ->setParameter('listing', $listing)
            ->getQuery()
            ->getResult();
    }

    /**
     * 检查商家上架配置是否已关联到某渠道商品
     */
    public function findOneByProductAndListing(
        ChannelProduct $product,
        InventoryListing $listing
    ): ?ChannelProductSource {
        return $this->findOneBy([
            'channelProduct' => $product,
            'inventoryListing' => $listing,
        ]);
    }

    /**
     * 获取或创建来源.
     */
    public function findOrCreate(
        ChannelProduct $product,
        InventoryListing $listing
    ): ChannelProductSource {
        $source = $this->findOneByProductAndListing($product, $listing);

        if ($source === null) {
            $source = new ChannelProductSource();
            $source->setChannelProduct($product);
            $source->setInventoryListing($listing);
        }

        return $source;
    }

    /**
     * 获取商家为某渠道商品提供的所有来源.
     *
     * @return ChannelProductSource[]
     */
    public function findByProductAndMerchant(ChannelProduct $product, Merchant $merchant): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.inventoryListing', 'l')
            ->join('l.merchantInventory', 'i')
            ->andWhere('s.channelProduct = :product')
            ->andWhere('i.merchant = :merchant')
            ->setParameter('product', $product)
            ->setParameter('merchant', $merchant)
            ->orderBy('s.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取商家的所有供货来源.
     *
     * @return ChannelProductSource[]
     */
    public function findByMerchant(Merchant $merchant): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.inventoryListing', 'l')
            ->join('l.merchantInventory', 'i')
            ->andWhere('i.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->orderBy('s.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 统计渠道商品的活跃来源数量.
     */
    public function countActiveByProduct(ChannelProduct $product): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.channelProduct = :product')
            ->andWhere('s.isActive = true')
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * 获取按优先级排序的第一个活跃来源（用于发货）.
     */
    public function findFirstActiveSource(ChannelProduct $product): ?ChannelProductSource
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.channelProduct = :product')
            ->andWhere('s.isActive = true')
            ->setParameter('product', $product)
            ->orderBy('s.priority', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 获取有库存的来源（按优先级排序）.
     *
     * @return ChannelProductSource[]
     */
    public function findAvailableSourcesByProduct(ChannelProduct $product): array
    {
        // 需要在应用层过滤，因为库存计算涉及多表
        $sources = $this->findActiveByProduct($product);

        return array_filter($sources, fn (ChannelProductSource $s) => $s->getAvailableQuantity() > 0);
    }
}
