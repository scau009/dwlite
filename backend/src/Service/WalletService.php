<?php

namespace App\Service;

use App\Entity\Merchant;
use App\Entity\Wallet;
use App\Entity\WalletTransaction;
use App\Repository\WalletRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class WalletService
{
    public function __construct(
        private WalletRepository $walletRepository,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
        private BusinessNoGenerator $businessNoGenerator,
    ) {
    }

    /**
     * 初始化商户钱包（保证金钱包和余额钱包）
     */
    public function initWallets(Merchant $merchant): array
    {
        // 检查是否已有钱包
        $existingWallets = $this->walletRepository->findByMerchant($merchant);
        if (count($existingWallets) > 0) {
            throw new \InvalidArgumentException($this->translator->trans('wallet.already_initialized'));
        }

        $wallets = $this->walletRepository->createWalletsForMerchant($merchant);
        $this->entityManager->flush();

        return $wallets;
    }

    /**
     * 保证金充值
     */
    public function chargeDeposit(
        Merchant $merchant,
        string $amount,
        ?string $remark = null,
        ?string $operatorId = null
    ): WalletTransaction {
        $wallet = $this->walletRepository->findDepositWallet($merchant);

        if ($wallet === null) {
            throw new \InvalidArgumentException($this->translator->trans('wallet.deposit_not_found'));
        }

        if (bccomp($amount, '0', 2) <= 0) {
            throw new \InvalidArgumentException($this->translator->trans('wallet.amount_positive'));
        }

        // 记录变动前余额
        $balanceBefore = $wallet->getBalance();

        // 增加余额
        $wallet->credit($amount);

        // 创建交易记录
        $transaction = new WalletTransaction();
        $transaction->setTransactionNo($this->businessNoGenerator->generateWalletTransactionNo())
            ->setWallet($wallet)
            ->setType(WalletTransaction::TYPE_CREDIT)
            ->setAmount($amount)
            ->setBalanceBefore($balanceBefore)
            ->setBalanceAfter($wallet->getBalance())
            ->setBizType(WalletTransaction::BIZ_DEPOSIT_CHARGE)
            ->setRemark($remark)
            ->setOperatorId($operatorId);

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        return $transaction;
    }

    /**
     * 获取保证金钱包信息
     */
    public function getDepositWallet(Merchant $merchant): ?Wallet
    {
        return $this->walletRepository->findDepositWallet($merchant);
    }

    /**
     * 获取余额钱包信息
     */
    public function getBalanceWallet(Merchant $merchant): ?Wallet
    {
        return $this->walletRepository->findBalanceWallet($merchant);
    }
}
