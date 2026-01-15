<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Settlement;
use App\Entity\SettlementItem;
use App\Message\CreateSettlementMessage;
use App\Repository\FulfillmentRepository;
use App\Repository\SettlementRepository;
use App\Service\BusinessNoGenerator;
use App\Service\OpenApi\WebhookService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * 创建结算单处理器 - 处理履约单完成后的结算单创建.
 */
#[AsMessageHandler]
class CreateSettlementMessageHandler
{
    private const LOCK_TTL = 300; // 5分钟

    public function __construct(
        private EntityManagerInterface $entityManager,
        private FulfillmentRepository $fulfillmentRepository,
        private SettlementRepository $settlementRepository,
        private BusinessNoGenerator $businessNoGenerator,
        private LockFactory $lockFactory,
        private WebhookService $webhookService,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(CreateSettlementMessage $message): void
    {
        // 分布式锁防重复
        $lock = $this->lockFactory->createLock(
            sprintf('settlement:create:%s', $message->fulfillmentId),
            self::LOCK_TTL
        );
        if (!$lock->acquire(false)) {
            $this->logger->info('Settlement creation already in progress', [
                'fulfillmentId' => $message->fulfillmentId,
            ]);

            return;
        }

        try {
            // 加载履约单
            $fulfillment = $this->fulfillmentRepository->find($message->fulfillmentId);
            if ($fulfillment === null) {
                $this->logger->warning('Fulfillment not found', [
                    'fulfillmentId' => $message->fulfillmentId,
                ]);

                return;
            }

            if (!$fulfillment->isCompleted()) {
                $this->logger->warning('Fulfillment not completed', [
                    'fulfillmentId' => $message->fulfillmentId,
                    'status' => $fulfillment->getStatus(),
                ]);

                return;
            }

            // 检查是否已存在结算单
            $existing = $this->settlementRepository->findOneBy(['fulfillment' => $fulfillment]);
            if ($existing !== null) {
                $this->logger->info('Settlement already exists', [
                    'fulfillmentId' => $message->fulfillmentId,
                    'settlementId' => $existing->getId(),
                ]);

                return;
            }

            // 创建结算单
            $settlement = new Settlement();
            $settlement->setSettlementNo($this->businessNoGenerator->generateSettlementNo())
                ->setMerchant($fulfillment->getMerchant())
                ->setFulfillment($fulfillment)
                ->setOrder($fulfillment->getOrder())
                ->setSettlementDays(7) // 可从配置读取
                ->setCurrency('CNY')
                ->setCommissionRate('5.00'); // 将从 FulfillmentItem 汇总

            // 设置预计结算时间
            $settlement->calculateScheduledSettleAt($fulfillment->getCompletedAt());

            // 从履约明细创建结算明细
            foreach ($fulfillment->getItems() as $fulfillmentItem) {
                $item = new SettlementItem();
                $commissionRate = $fulfillmentItem->getCommissionRate() ?? '5.00'; // 默认5%
                $item->snapshotFromFulfillmentItem($fulfillmentItem, $commissionRate);
                $settlement->addItem($item);
            }

            // 计算金额
            $settlement->calculateAmounts();

            // 持久化
            $this->entityManager->persist($settlement);
            $this->entityManager->flush();

            $this->logger->info('Settlement created', [
                'settlementId' => $settlement->getId(),
                'settlementNo' => $settlement->getSettlementNo(),
                'fulfillmentId' => $fulfillment->getId(),
                'grossAmount' => $settlement->getGrossAmount(),
                'netAmount' => $settlement->getNetAmount(),
                'scheduledSettleAt' => $settlement->getScheduledSettleAt()->format(\DateTimeInterface::ATOM),
            ]);

            // Trigger webhook for merchant
            $this->webhookService->triggerMerchantEvent(
                \App\Entity\Webhook::EVENT_SETTLEMENT_CREATED,
                $settlement->getMerchant(),
                [
                    'settlement_no' => $settlement->getSettlementNo(),
                    'fulfillment_no' => $fulfillment->getFulfillmentNo(),
                    'order_external_id' => $fulfillment->getOrder()->getExternalOrderId(),
                    'sales_channel' => $fulfillment->getOrder()->getSalesChannel()->getCode(),
                    'gross_amount' => $settlement->getGrossAmount(),
                    'commission_amount' => $settlement->getCommissionAmount(),
                    'net_amount' => $settlement->getNetAmount(),
                    'currency' => $settlement->getCurrency(),
                    'scheduled_settle_at' => $settlement->getScheduledSettleAt()->format(\DateTimeInterface::ATOM),
                    'settlement_days' => $settlement->getSettlementDays(),
                    'status' => $settlement->getStatus(),
                ]
            );
        } finally {
            $lock->release();
        }
    }
}
