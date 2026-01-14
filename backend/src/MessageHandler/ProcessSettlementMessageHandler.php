<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\WalletTransaction;
use App\Message\ProcessSettlementMessage;
use App\Repository\SettlementRepository;
use App\Repository\WalletRepository;
use App\Service\BusinessNoGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * 处理结算入账处理器 - 处理 T+N 到期的结算单入账.
 */
#[AsMessageHandler]
class ProcessSettlementMessageHandler
{
    private const LOCK_TTL = 600; // 10分钟

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SettlementRepository $settlementRepository,
        private WalletRepository $walletRepository,
        private BusinessNoGenerator $businessNoGenerator,
        private LockFactory $lockFactory,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ProcessSettlementMessage $message): void
    {
        $lock = $this->lockFactory->createLock(
            sprintf('settlement:process:%s', $message->settlementId),
            self::LOCK_TTL
        );
        if (!$lock->acquire(false)) {
            $this->logger->info('Settlement processing already in progress', [
                'settlementId' => $message->settlementId,
            ]);

            return;
        }

        try {
            $settlement = $this->settlementRepository->find($message->settlementId);
            if ($settlement === null) {
                $this->logger->warning('Settlement not found', [
                    'settlementId' => $message->settlementId,
                ]);

                return;
            }

            if (!$settlement->canSettle()) {
                $this->logger->info('Settlement cannot be processed', [
                    'settlementId' => $message->settlementId,
                    'status' => $settlement->getStatus(),
                    'scheduledSettleAt' => $settlement->getScheduledSettleAt()->format(\DateTimeInterface::ATOM),
                ]);

                return;
            }

            // 获取商户余额钱包
            $wallet = $this->walletRepository->findBalanceWallet($settlement->getMerchant());
            if ($wallet === null) {
                $this->logger->error('Balance wallet not found', [
                    'settlementId' => $settlement->getId(),
                    'merchantId' => $settlement->getMerchant()->getId(),
                ]);

                throw new \RuntimeException('Balance wallet not found');
            }

            // 记录变动前余额
            $balanceBefore = $wallet->getBalance();

            // 入账
            $wallet->credit($settlement->getNetAmount());

            // 创建钱包交易记录
            $transaction = new WalletTransaction();
            $transaction->setTransactionNo($this->businessNoGenerator->generateWalletTransactionNo())
                ->setWallet($wallet)
                ->setType(WalletTransaction::TYPE_CREDIT)
                ->setAmount($settlement->getNetAmount())
                ->setBalanceBefore($balanceBefore)
                ->setBalanceAfter($wallet->getBalance())
                ->setBizType(WalletTransaction::BIZ_SETTLEMENT)
                ->setBizId($settlement->getId())
                ->setRemark(sprintf('结算单 %s 入账', $settlement->getSettlementNo()));

            // 标记结算单已结算
            $settlement->markSettled($transaction->getId());

            $this->entityManager->persist($transaction);
            $this->entityManager->flush();

            $this->logger->info('Settlement processed', [
                'settlementId' => $settlement->getId(),
                'settlementNo' => $settlement->getSettlementNo(),
                'netAmount' => $settlement->getNetAmount(),
                'transactionId' => $transaction->getId(),
                'balanceBefore' => $balanceBefore,
                'balanceAfter' => $wallet->getBalance(),
            ]);
        } finally {
            $lock->release();
        }
    }
}
