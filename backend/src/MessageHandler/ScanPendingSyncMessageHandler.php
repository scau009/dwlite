<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PushChannelProductMessage;
use App\Message\ScanPendingSyncMessage;
use App\Repository\ChannelProductRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handler for ScanPendingSyncMessage.
 *
 * Compensation mechanism that scans for stale pending sync products
 * and re-triggers their sync. This catches any products that got
 * stuck due to message loss or processing failures.
 */
#[AsMessageHandler]
class ScanPendingSyncMessageHandler
{
    public function __construct(
        private ChannelProductRepository $channelProductRepo,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ScanPendingSyncMessage $message): void
    {
        $this->logger->info('Scanning for stale pending sync products', [
            'threshold' => $message->getThreshold()->format(\DateTimeInterface::ATOM),
            'salesChannelId' => $message->salesChannelId,
            'limit' => $message->limit,
        ]);

        // Find products that are stuck in pending status
        $staleProducts = $this->channelProductRepo->findStalePending(
            $message->getThreshold(),
            $message->salesChannelId,
            $message->limit,
        );

        if (empty($staleProducts)) {
            $this->logger->info('No stale pending products found');

            return;
        }

        $this->logger->info('Found stale pending products', [
            'count' => count($staleProducts),
        ]);

        $dispatched = 0;
        foreach ($staleProducts as $product) {
            try {
                // Determine operation based on product state
                $operation = $product->getExternalId() !== null
                    ? PushChannelProductMessage::OPERATION_UPDATE_STOCK_PRICE
                    : PushChannelProductMessage::OPERATION_PUSH_PRODUCT;

                $this->messageBus->dispatch(new PushChannelProductMessage(
                    $product->getId(),
                    $operation,
                ));

                ++$dispatched;

                $this->logger->debug('Re-dispatched sync for stale product', [
                    'channelProductId' => $product->getId(),
                    'operation' => $operation,
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to re-dispatch sync for stale product', [
                    'channelProductId' => $product->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Completed stale product scan', [
            'found' => count($staleProducts),
            'dispatched' => $dispatched,
        ]);
    }
}
