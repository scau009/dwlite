<?php

namespace App\Repository;

use App\Entity\Merchant;
use App\Entity\MerchantSalesChannel;
use App\Entity\SalesChannel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MerchantSalesChannel>
 */
class MerchantSalesChannelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MerchantSalesChannel::class);
    }

    public function save(MerchantSalesChannel $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(MerchantSalesChannel $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByMerchant(Merchant $merchant): array
    {
        return $this->createQueryBuilder('mc')
            ->innerJoin('mc.salesChannel', 'c')
            ->where('mc.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->orderBy('c.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveByMerchant(Merchant $merchant): array
    {
        return $this->createQueryBuilder('mc')
            ->innerJoin('mc.salesChannel', 'c')
            ->where('mc.merchant = :merchant')
            ->andWhere('mc.status = :status')
            ->andWhere('c.status = :channelStatus')
            ->setParameter('merchant', $merchant)
            ->setParameter('status', MerchantSalesChannel::STATUS_ACTIVE)
            ->setParameter('channelStatus', SalesChannel::STATUS_ACTIVE)
            ->orderBy('c.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByMerchantAndChannel(Merchant $merchant, SalesChannel $channel): ?MerchantSalesChannel
    {
        return $this->findOneBy([
            'merchant' => $merchant,
            'salesChannel' => $channel,
        ]);
    }

    public function findPendingApproval(): array
    {
        return $this->createQueryBuilder('mc')
            ->innerJoin('mc.merchant', 'm')
            ->innerJoin('mc.salesChannel', 'c')
            ->where('mc.status = :status')
            ->setParameter('status', MerchantSalesChannel::STATUS_PENDING)
            ->orderBy('mc.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySalesChannel(SalesChannel $channel): array
    {
        return $this->createQueryBuilder('mc')
            ->where('mc.salesChannel = :channel')
            ->andWhere('mc.status = :status')
            ->setParameter('channel', $channel)
            ->setParameter('status', MerchantSalesChannel::STATUS_ACTIVE)
            ->getQuery()
            ->getResult();
    }
}
