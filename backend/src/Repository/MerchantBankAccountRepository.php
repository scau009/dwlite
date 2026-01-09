<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Merchant;
use App\Entity\MerchantBankAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MerchantBankAccount>
 */
class MerchantBankAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MerchantBankAccount::class);
    }

    /**
     * 获取商户的所有银行账户.
     *
     * @return MerchantBankAccount[]
     */
    public function findByMerchant(Merchant $merchant): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->orderBy('a.isDefault', 'DESC')
            ->addOrderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 获取商户的默认银行账户.
     */
    public function findDefaultByMerchant(Merchant $merchant): ?MerchantBankAccount
    {
        return $this->createQueryBuilder('a')
            ->where('a.merchant = :merchant')
            ->andWhere('a.isDefault = true')
            ->andWhere('a.status = :status')
            ->setParameter('merchant', $merchant)
            ->setParameter('status', MerchantBankAccount::STATUS_ACTIVE)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * 获取商户可用于提现的银行账户.
     *
     * @return MerchantBankAccount[]
     */
    public function findActiveByMerchant(Merchant $merchant): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.merchant = :merchant')
            ->andWhere('a.status = :status')
            ->setParameter('merchant', $merchant)
            ->setParameter('status', MerchantBankAccount::STATUS_ACTIVE)
            ->orderBy('a.isDefault', 'DESC')
            ->addOrderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 统计商户的银行账户数量.
     */
    public function countByMerchant(Merchant $merchant): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * 保存银行账户.
     */
    public function save(MerchantBankAccount $account, bool $flush = false): void
    {
        $this->getEntityManager()->persist($account);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除银行账户.
     */
    public function remove(MerchantBankAccount $account, bool $flush = false): void
    {
        $this->getEntityManager()->remove($account);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 清除商户的默认账户标记.
     */
    public function clearDefaultByMerchant(Merchant $merchant): void
    {
        $this->createQueryBuilder('a')
            ->update()
            ->set('a.isDefault', 'false')
            ->where('a.merchant = :merchant')
            ->setParameter('merchant', $merchant)
            ->getQuery()
            ->execute();
    }
}
