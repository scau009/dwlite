<?php

declare(strict_types=1);

namespace App\Service\Settlement;

use App\Entity\Settlement;
use App\Entity\WalletTransaction;
use App\Entity\Webhook;
use App\Repository\WalletRepository;
use App\Service\BusinessNoGenerator;
use App\Service\OpenApi\WebhookService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * Settlement processing service - handles crediting merchant wallet and marking settlements as settled.
 */
class SettlementService
{
    private const LOCK_TTL = 600; // 10 minutes

    public function __construct(
        private EntityManagerInterface $entityManager,
        private WalletRepository $walletRepository,
        private BusinessNoGenerator $businessNoGenerator,
        private WebhookService $webhookService,
        private LockFactory $lockFactory,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Process settlement - credits merchant wallet and marks as settled.
     *
     * @param bool $force If true, skip the scheduledSettleAt check (for manual settlement)
     *
     * @throws \RuntimeException If settlement cannot be processed
     */
    public function settle(Settlement $settlement, bool $force = false): void
    {
        $lock = $this->lockFactory->createLock(
            sprintf('settlement:process:%s', $settlement->getId()),
            self::LOCK_TTL
        );
        if (!$lock->acquire(false)) {
            $this->logger->info('Settlement processing already in progress', [
                'settlementId' => $settlement->getId(),
            ]);

            throw new \RuntimeException('Settlement processing already in progress');
        }

        try {
            // Validate settlement status
            if (!$settlement->isPending()) {
                $this->logger->info('Settlement is not pending', [
                    'settlementId' => $settlement->getId(),
                    'status' => $settlement->getStatus(),
                ]);

                throw new \RuntimeException('Settlement is not pending');
            }

            // Check scheduled time (skip if force=true for manual settlement)
            if (!$force && !$settlement->canSettle()) {
                $this->logger->info('Settlement scheduled time has not arrived', [
                    'settlementId' => $settlement->getId(),
                    'scheduledSettleAt' => $settlement->getScheduledSettleAt()->format(\DateTimeInterface::ATOM),
                ]);

                throw new \RuntimeException('Settlement scheduled time has not arrived');
            }

            // Get merchant balance wallet
            $wallet = $this->walletRepository->findBalanceWallet($settlement->getMerchant());
            if ($wallet === null) {
                $this->logger->error('Balance wallet not found', [
                    'settlementId' => $settlement->getId(),
                    'merchantId' => $settlement->getMerchant()->getId(),
                ]);

                throw new \RuntimeException('Balance wallet not found');
            }

            // Record balance before
            $balanceBefore = $wallet->getBalance();

            // Credit wallet
            $wallet->credit($settlement->getNetAmount());

            // Create wallet transaction record
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

            // Mark settlement as settled
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
                'force' => $force,
            ]);

            // Trigger webhook for merchant
            $fulfillment = $settlement->getFulfillment();
            $order = $settlement->getOrder();
            $this->webhookService->triggerMerchantEvent(
                Webhook::EVENT_SETTLEMENT_COMPLETED,
                $settlement->getMerchant(),
                [
                    'settlement_no' => $settlement->getSettlementNo(),
                    'fulfillment_no' => $fulfillment->getFulfillmentNo(),
                    'order_external_id' => $order->getExternalOrderId(),
                    'net_amount' => $settlement->getNetAmount(),
                    'currency' => $settlement->getCurrency(),
                    'wallet_transaction_id' => $transaction->getId(),
                    'balance_before' => $balanceBefore,
                    'balance_after' => $wallet->getBalance(),
                    'settled_at' => $settlement->getSettledAt()?->format(\DateTimeInterface::ATOM),
                    'status' => $settlement->getStatus(),
                ]
            );
        } finally {
            $lock->release();
        }
    }
}
