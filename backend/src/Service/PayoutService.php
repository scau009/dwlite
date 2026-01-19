<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Merchant;
use App\Entity\MerchantBankAccount;
use App\Entity\Payout;
use App\Repository\MerchantBankAccountRepository;
use App\Repository\PayoutRepository;
use Doctrine\ORM\EntityManagerInterface;

class PayoutService
{
    public function __construct(
        private readonly PayoutRepository $payoutRepository,
        private readonly MerchantBankAccountRepository $bankAccountRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * 创建提现申请.
     *
     * @throws \InvalidArgumentException
     */
    public function createPayout(
        Merchant $merchant,
        string $bankAccountId,
        string $amount,
        ?string $remark = null,
    ): Payout {
        // 1. 验证银行账户
        $bankAccount = $this->bankAccountRepository->find($bankAccountId);
        if (!$bankAccount instanceof MerchantBankAccount) {
            throw new \InvalidArgumentException('error.bank_account_not_found');
        }

        // 验证账户属于该商户
        if ($bankAccount->getMerchant()->getId() !== $merchant->getId()) {
            throw new \InvalidArgumentException('error.bank_account_not_belong');
        }

        // 验证账户状态
        if (!$bankAccount->canBeUsedForPayout()) {
            throw new \InvalidArgumentException('error.bank_account_not_active');
        }

        // 2. 获取余额钱包并验证余额
        $balanceWallet = $merchant->getBalanceWallet();
        if ($balanceWallet === null) {
            throw new \InvalidArgumentException('error.wallet_not_found');
        }

        if (!$balanceWallet->canWithdraw()) {
            throw new \InvalidArgumentException('error.wallet_cannot_withdraw');
        }

        $availableBalance = $balanceWallet->getAvailableBalance();
        if (bccomp($availableBalance, $amount, 2) < 0) {
            throw new \InvalidArgumentException('error.insufficient_balance');
        }

        // 3. 生成提现单号
        $payoutNo = $this->generatePayoutNo();

        // 4. 创建提现申请
        $payout = new Payout();
        $payout->setPayoutNo($payoutNo);
        $payout->setMerchant($merchant);
        $payout->setAmount($amount);
        $payout->setFee('0.00'); // 暂时不收手续费，后续可从配置读取
        $payout->calculateActualAmount();
        $payout->setCurrency($bankAccount->getCurrency());
        $payout->snapshotBankAccount($bankAccount);

        if ($remark !== null) {
            $payout->setRemark($remark);
        }

        // 5. 保存
        $this->payoutRepository->save($payout, true);

        return $payout;
    }

    /**
     * 生成提现单号.
     * 格式: WD + 年月日 + 6位序列号.
     */
    private function generatePayoutNo(): string
    {
        $date = date('Ymd');
        $prefix = 'WD'.$date;

        // 获取当天最大序列号
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT MAX(SUBSTRING(payout_no, 11)) as max_seq FROM payouts WHERE payout_no LIKE :prefix';
        $result = $conn->executeQuery($sql, ['prefix' => $prefix.'%'])->fetchAssociative();

        $maxSeq = $result['max_seq'] ?? 0;
        $nextSeq = (int) $maxSeq + 1;

        return $prefix.str_pad((string) $nextSeq, 6, '0', STR_PAD_LEFT);
    }

    /**
     * 获取商户的提现统计摘要.
     *
     * @return array{availableBalance: string, processingAmount: string, pendingAmount: string}
     */
    public function getPayoutSummary(Merchant $merchant): array
    {
        // 获取可提现余额
        $balanceWallet = $merchant->getBalanceWallet();
        $availableBalance = $balanceWallet?->getAvailableBalance() ?? '0.00';

        // 获取各状态的提现金额
        $sums = $this->payoutRepository->sumByMerchant($merchant);

        return [
            'availableBalance' => $availableBalance,
            'processingAmount' => bcadd($sums['pending'], $sums['processing'], 2),
            'pendingAmount' => $sums['pending'],
        ];
    }
}
