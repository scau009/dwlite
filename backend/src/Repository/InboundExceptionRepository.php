<?php

namespace App\Repository;

use App\Entity\InboundException;
use App\Entity\Merchant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InboundException>
 */
class InboundExceptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InboundException::class);
    }

    /**
     * 获取商户的异常单列表
     *
     * @return InboundException[]
     */
    public function findByMerchant(Merchant $merchant, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->orderBy('e.createdAt', 'DESC');

        if ($status !== null) {
            $qb->andWhere('e.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * 获取待处理的异常单
     *
     * @return InboundException[]
     */
    public function findPending(Merchant $merchant): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.merchant = :merchant')
            ->andWhere('e.status IN (:statuses)')
            ->setParameter('merchant', $merchant)
            ->setParameter('statuses', [
                InboundException::STATUS_PENDING,
                InboundException::STATUS_PROCESSING,
            ])
            ->orderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 按异常单号查找
     */
    public function findByExceptionNo(string $exceptionNo): ?InboundException
    {
        return $this->findOneBy(['exceptionNo' => $exceptionNo]);
    }

    /**
     * 统计商户待处理的异常单数量
     */
    public function countPendingByMerchant(Merchant $merchant): int
    {
        return $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.merchant = :merchant')
            ->andWhere('e.status IN (:statuses)')
            ->setParameter('merchant', $merchant)
            ->setParameter('statuses', [
                InboundException::STATUS_PENDING,
                InboundException::STATUS_PROCESSING,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }
}
