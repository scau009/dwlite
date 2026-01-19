<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PullOrdersMessage;
use App\Message\ScheduleOrderPullMessage;
use App\Repository\SalesChannelRepository;
use App\Service\ChannelGateway\ChannelGatewayInterface;
use App\Service\ChannelGateway\ChannelGatewayRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class ScheduleOrderPullMessageHandler
{
    private const DEFAULT_LOOKBACK_MINUTES = 10;

    public function __construct(
        private readonly SalesChannelRepository $salesChannelRepo,
        private readonly ChannelGatewayRegistry $gatewayRegistry,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ScheduleOrderPullMessage $message): void
    {
        $this->logger->info('Scheduling order pull for all channels');

        $activeChannels = $this->salesChannelRepo->findActive();
        $dispatchedCount = 0;

        foreach ($activeChannels as $channel) {
            // 跳过没有 gateway 或不支持拉取订单的渠道
            if (!$this->gatewayRegistry->has($channel->getCode())) {
                continue;
            }

            $gateway = $this->gatewayRegistry->get($channel->getCode());
            if (!$gateway->supports(ChannelGatewayInterface::OPERATION_PULL_ORDERS)) {
                continue;
            }

            try {
                $this->messageBus->dispatch(
                    PullOrdersMessage::createScheduled(
                        $channel->getId(),
                        self::DEFAULT_LOOKBACK_MINUTES,
                    )
                );
                ++$dispatchedCount;

                $this->logger->info('Dispatched order pull for channel', [
                    'channelId' => $channel->getId(),
                    'channelCode' => $channel->getCode(),
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to dispatch order pull', [
                    'channelId' => $channel->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Order pull scheduling completed', [
            'totalChannels' => count($activeChannels),
            'dispatched' => $dispatchedCount,
        ]);
    }
}
