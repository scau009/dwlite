<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ProcessSettlementMessage;
use App\Message\ScanPendingSettlementsMessage;
use App\Repository\SettlementRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * 扫描待结算单处理器 - 定时扫描待结算的结算单并触发入账.
 */
#[AsMessageHandler]
class ScanPendingSettlementsMessageHandler
{
    public function __construct(
        private SettlementRepository $settlementRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ScanPendingSettlementsMessage $message): void
    {
        // 查询待结算的结算单
        $settlements = $this->settlementRepository->findReadyToSettle(100);

        $this->logger->info('Scanning pending settlements', [
            'count' => count($settlements),
            'scheduledAt' => $message->scheduledAt->format(\DateTimeInterface::ATOM),
        ]);

        foreach ($settlements as $settlement) {
            $this->messageBus->dispatch(ProcessSettlementMessage::create($settlement->getId()));
        }

        if (count($settlements) > 0) {
            $this->logger->info('Dispatched settlement processing messages', [
                'count' => count($settlements),
            ]);
        }
    }
}
