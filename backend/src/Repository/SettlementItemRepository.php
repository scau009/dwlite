<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Settlement;
use App\Entity\SettlementItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SettlementItem>
 */
class SettlementItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SettlementItem::class);
    }

    /**
     * 获取结算单的所有明细.
     *
     * @return SettlementItem[]
     */
    public function findBySettlement(Settlement $settlement): array
    {
        return $this->createQueryBuilder('si')
            ->where('si.settlement = :settlement')
            ->setParameter('settlement', $settlement)
            ->orderBy('si.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
