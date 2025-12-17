<?php

namespace App\Repository;

use App\Entity\Merchant;
use App\Entity\MerchantProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MerchantProduct>
 */
class MerchantProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MerchantProduct::class);
    }

    /**
     * 获取商户的选品列表
     *
     * @return MerchantProduct[]
     */
    public function findByMerchant(Merchant $merchant, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('mp')
            ->andWhere('mp.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->orderBy('mp.selectedAt', 'DESC');

        if ($status !== null) {
            $qb->andWhere('mp.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 检查商户是否已选择某 SKU
     */
    public function isSkuSelected(Merchant $merchant, string $skuId): bool
    {
        $count = $this->createQueryBuilder('mp')
            ->select('COUNT(mp.id)')
            ->andWhere('mp.merchant = :merchant')
            ->andWhere('mp.productSku = :skuId')
            ->setParameter('merchant', $merchant)
            ->setParameter('skuId', $skuId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * 获取商户活跃选品数量
     */
    public function countActiveByMerchant(Merchant $merchant): int
    {
        return $this->createQueryBuilder('mp')
            ->select('COUNT(mp.id)')
            ->andWhere('mp.merchant = :merchant')
            ->andWhere('mp.status = :status')
            ->setParameter('merchant', $merchant)
            ->setParameter('status', MerchantProduct::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
