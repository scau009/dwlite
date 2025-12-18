<?php

namespace App\Repository;

use App\Entity\Wallet;
use App\Entity\WalletTransaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WalletTransaction>
 */
class WalletTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WalletTransaction::class);
    }

    public function save(WalletTransaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByWallet(Wallet $wallet, int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.wallet = :wallet')
            ->setParameter('wallet', $wallet)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * 分页查询钱包交易记录
     *
     * @return array ['data' => WalletTransaction[], 'total' => int]
     */
    public function findByWalletPaginated(Wallet $wallet, int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.wallet = :wallet')
            ->setParameter('wallet', $wallet)
            ->orderBy('t.createdAt', 'DESC');

        // 获取总数
        $countQb = clone $qb;
        $total = $countQb->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // 分页
        $offset = ($page - 1) * $limit;
        $data = $qb->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'data' => $data,
            'total' => (int) $total,
        ];
    }

    public function findByBiz(string $bizType, string $bizId): array
    {
        return $this->findBy([
            'bizType' => $bizType,
            'bizId' => $bizId,
        ], ['createdAt' => 'DESC']);
    }

    /**
     * 创建交易记录
     */
    public function createTransaction(
        Wallet $wallet,
        string $type,
        string $amount,
        string $bizType,
        ?string $bizId = null,
        ?string $remark = null,
        ?string $operatorId = null
    ): WalletTransaction {
        $balanceBefore = $wallet->getBalance();

        $transaction = (new WalletTransaction())
            ->setWallet($wallet)
            ->setType($type)
            ->setAmount($amount)
            ->setBalanceBefore($balanceBefore)
            ->setBizType($bizType)
            ->setBizId($bizId)
            ->setRemark($remark)
            ->setOperatorId($operatorId);

        // 根据类型更新钱包余额
        if ($type === WalletTransaction::TYPE_CREDIT) {
            $wallet->credit($amount);
        } elseif ($type === WalletTransaction::TYPE_DEBIT) {
            $wallet->debit($amount);
        } elseif ($type === WalletTransaction::TYPE_FREEZE) {
            $wallet->freeze($amount);
        } elseif ($type === WalletTransaction::TYPE_UNFREEZE) {
            $wallet->unfreeze($amount);
        }

        $transaction->setBalanceAfter($wallet->getBalance());

        $this->getEntityManager()->persist($transaction);

        return $transaction;
    }
}