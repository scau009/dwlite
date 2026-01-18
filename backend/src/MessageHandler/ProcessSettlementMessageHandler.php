<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ProcessSettlementMessage;
use App\Repository\SettlementRepository;
use App\Service\Settlement\SettlementService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * 处理结算入账处理器 - 处理 T+N 到期的结算单入账.
 */
#[AsMessageHandler]
class ProcessSettlementMessageHandler
{
    public function __construct(
        private SettlementRepository $settlementRepository,
        private SettlementService $settlementService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProcessSettlementMessage $message): void
    {
        $settlement = $this->settlementRepository->find($message->settlementId);
        if ($settlement === null) {
            $this->logger->warning('Settlement not found', [
                'settlementId' => $message->settlementId,
            ]);

            return;
        }

        try {
            $this->settlementService->settle($settlement, force: false);
        } catch (\RuntimeException $e) {
            // Log but don't rethrow - settlement might not be ready yet or already processed
            $this->logger->info('Settlement could not be processed', [
                'settlementId' => $message->settlementId,
                'reason' => $e->getMessage(),
            ]);
        }
    }
}
