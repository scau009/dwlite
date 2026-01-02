<?php

namespace App\Repository;

use App\Entity\Merchant;
use App\Entity\Wallet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Wallet>
 */
class WalletRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Wallet::class);
    }

    public function save(Wallet $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByMerchant(Merchant $merchant): array
    {
        return $this->findBy(['merchant' => $merchant]);
    }

    public function findDepositWallet(Merchant $merchant): ?Wallet
    {
        return $this->findOneBy([
            'merchant' => $merchant,
            'type' => Wallet::TYPE_DEPOSIT,
        ]);
    }

    public function findBalanceWallet(Merchant $merchant): ?Wallet
    {
        return $this->findOneBy([
            'merchant' => $merchant,
            'type' => Wallet::TYPE_BALANCE,
        ]);
    }

    /**
     * 为商户创建两个钱包.
     */
    public function createWalletsForMerchant(Merchant $merchant): array
    {
        $depositWallet = (new Wallet())
            ->setMerchant($merchant)
            ->setType(Wallet::TYPE_DEPOSIT);

        $balanceWallet = (new Wallet())
            ->setMerchant($merchant)
            ->setType(Wallet::TYPE_BALANCE);

        $this->getEntityManager()->persist($depositWallet);
        $this->getEntityManager()->persist($balanceWallet);

        return [$depositWallet, $balanceWallet];
    }
}
