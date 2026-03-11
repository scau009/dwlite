<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PushChannelProductMessage;
use App\Repository\ChannelProductRepository;
use App\Service\ChannelGateway\Exception\ChannelGatewayException;
use App\Service\ChannelProductSyncService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for PushChannelProductMessage.
 *
 * Pushes channel product to external sales channel:
 * 1. Acquires distributed lock
 * 2. Calls ChannelGateway to sync product
 * 3. Updates sync status and external ID
 * 4. Records sync log
 */
#[AsMessageHandler]
class PushChannelProductMessageHandler
{
    public function __construct(
        private ChannelProductRepository $channelProductRepo,
        private ChannelProductSyncService $syncService,
        private LockFactory $lockFactory,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(PushChannelProductMessage $message): void
    {
        $this->logger->info('Processing push to channel', [
            'channelProductId' => $message->channelProductId,
            'operation' => $message->operation,
        ]);

        // Acquire distributed lock
        $lock = $this->lockFactory->createLock(
            sprintf('push:channel:%s', $message->channelProductId),
            120  // TTL in seconds (longer for external API calls)
        );

        if (!$lock->acquire(false)) {
            $this->logger->info('Push already in progress, skipping', [
                'channelProductId' => $message->channelProductId,
            ]);

            return;
        }

        try {
            $this->processPush($message);
        } finally {
            $lock->release();
        }
    }

    private function processPush(PushChannelProductMessage $message): void
    {
        // Find channel product
        $channelProduct = $this->channelProductRepo->find($message->channelProductId);
        if ($channelProduct === null) {
            $this->logger->warning('ChannelProduct not found for push', [
                'id' => $message->channelProductId,
            ]);

            return;
        }

        try {
            // Perform push to external channel
            $syncLog = $this->syncService->pushToChannel(
                $channelProduct,
                $message->operation,
                $message->isReActive,
            );

            if ($syncLog->isSuccess()) {
                $this->logger->info('Push to channel succeeded', [
                    'channelProductId' => $channelProduct->getId(),
                    'operation' => $message->operation,
                    'externalId' => $channelProduct->getExternalId(),
                    'durationMs' => $syncLog->getDurationMs(),
                ]);
            } elseif ($syncLog->isSkipped()) {
                // Skipped is not a failure, don't retry (e.g., no gateway for channel)
                $this->logger->info('Push to channel skipped', [
                    'channelProductId' => $channelProduct->getId(),
                    'operation' => $message->operation,
                    'reason' => $syncLog->getErrorMessage(),
                ]);
            } else {
                $this->logger->warning('Push to channel failed', [
                    'channelProductId' => $channelProduct->getId(),
                    'operation' => $message->operation,
                    'errorCode' => $syncLog->getErrorCode(),
                    'errorMessage' => $syncLog->getErrorMessage(),
                ]);

                // Throw exception to trigger retry
                throw new ChannelGatewayException($syncLog->getErrorMessage() ?? 'Push to channel failed', $syncLog->getErrorCode() ?? 'PUSH_FAILED');
            }
        } catch (ChannelGatewayException $e) {
            $this->logger->error('Channel gateway error during push', [
                'channelProductId' => $channelProduct->getId(),
                'operation' => $message->operation,
                'error' => $e->getMessage(),
                'errorCode' => $e->getErrorCode(),
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error during push', [
                'channelProductId' => $channelProduct->getId(),
                'operation' => $message->operation,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
