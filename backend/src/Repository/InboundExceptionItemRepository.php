<?php

namespace App\Repository;

use App\Entity\InboundException;
use App\Entity\InboundExceptionItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InboundExceptionItem>
 */
class InboundExceptionItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InboundExceptionItem::class);
    }

    /**
     * 获取异常单的所有明细.
     *
     * @return InboundExceptionItem[]
     */
    public function findByException(InboundException $exception): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.inboundException = :exception')
            ->setParameter('exception', $exception)
            ->orderBy('i.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
