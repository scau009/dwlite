<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ChannelProduct;
use App\Entity\ChannelProductSource;
use App\Entity\ChannelProductSyncLog;
use App\Entity\InventoryListing;
use App\Entity\MerchantInventory;
use App\Enum\SyncTriggerSourceEnum;
use App\Message\SyncChannelProductMessage;
use App\Repository\ChannelProductRepository;
use App\Repository\ChannelProductSourceRepository;
use App\Repository\InventoryListingRepository;
use App\Service\ChannelGateway\ChannelGatewayContext;
use App\Service\ChannelGateway\ChannelGatewayInterface;
use App\Service\ChannelGateway\ChannelGatewayRegistry;
use App\Service\ChannelGateway\Dto\Request\ProductImageDto;
use App\Service\ChannelGateway\Dto\Request\ProductSkuDto;
use App\Service\ChannelGateway\Dto\Request\PushProductRequest;
use App\Service\ChannelGateway\Exception\ChannelGatewayException;
use Doctrine\ORM\EntityManagerInterface;
use Predis\Client as RedisClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Core service for channel product synchronization.
 *
 * Coordinates the sync flow:
 * 1. Merchant operations trigger async sync messages
 * 2. Aggregation recalculates stock and price
 * 3. Push to external channel via ChannelGateway
 */
class ChannelProductSyncService
{
    private const DEBOUNCE_TTL_SECONDS = 1;

    public function __construct(
        private ChannelProductRepository $channelProductRepo,
        private ChannelProductSourceRepository $sourceRepo,
        private InventoryListingRepository $listingRepo,
        private ChannelGatewayRegistry $gatewayRegistry,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private RedisClient $redis,
        private LoggerInterface $logger,
        private BusinessNoGenerator $businessNoGenerator,
    ) {}

    /**
     * Trigger sync from inventory listing change.
     *
     * Called by InventoryListingService after create/update/activate/pause/delete.
     */
    public function triggerSyncFromListing(
        InventoryListing $listing,
        SyncTriggerSourceEnum $triggerSource,
    ): void {
        // Find or create channel product
        $channelProduct = $this->findOrCreateChannelProduct($listing);
        if ($channelProduct === null) {
            return;
        }

        // Find or create source link
        $this->ensureSourceExists($channelProduct, $listing);

        // Dispatch with debounce
        $this->dispatchWithDebounce(
            $channelProduct,
            $triggerSource,
            $listing->getId(),
            $listing->getMerchantInventory()->getMerchant()->getId(),
            null,
        );
    }

    /**
     * Trigger sync from inventory change (inbound/outbound/adjust).
     *
     * Called by inventory services after stock changes.
     */
    public function triggerSyncFromInventory(
        MerchantInventory $inventory,
        SyncTriggerSourceEnum $triggerSource,
    ): void {
        // Find all listings for this inventory
        $listings = $this->listingRepo->findByInventory($inventory);

        foreach ($listings as $listing) {
            // Only process active listings
            if ($listing->getStatus() !== InventoryListing::STATUS_ACTIVE) {
                continue;
            }

            // Find the channel product
            $channelProduct = $this->findChannelProductForListing($listing);
            if ($channelProduct === null) {
                continue;
            }

            $this->dispatchWithDebounce(
                $channelProduct,
                $triggerSource,
                $listing->getId(),
                $listing->getMerchantInventory()->getMerchant()->getId(),
                $inventory->getId(),
            );
        }
    }

    /**
     * Trigger sync from channel product directly.
     *
     * Used for manual triggers or when listing is being deleted.
     */
    public function triggerSyncFromChannelProduct(
        ChannelProduct $channelProduct,
        SyncTriggerSourceEnum $triggerSource,
        ?string $merchantId = null,
    ): void {
        $this->dispatchWithDebounce(
            $channelProduct,
            $triggerSource,
            null,
            $merchantId,
            null,
        );
    }

    /**
     * Perform aggregation for a channel product.
     *
     * Called by SyncChannelProductMessageHandler.
     */
    public function aggregateChannelProduct(
        ChannelProduct $channelProduct,
        SyncTriggerSourceEnum $triggerSource,
        ?InventoryListing $triggerListing = null,
        ?string $triggerMerchantId = null,
        ?string $triggerInventoryId = null,
    ): ChannelProductSyncLog {
        // Create sync log
        $syncLog = ChannelProductSyncLog::createForAggregate(
            $channelProduct,
            $triggerSource,
            $triggerListing?->getId(),
            $triggerMerchantId,
            $triggerInventoryId,
        );
        $syncLog->markProcessing();

        $this->entityManager->persist($syncLog);

        try {
            // Sync ALL source statuses based on listing's actual status (self-healing).
            // Because debounce may skip intermediate messages, we must check all sources
            // instead of only the trigger listing to ensure complete self-healing.
            $this->syncAllSourceStatuses($channelProduct);

            // Recalculate aggregated stock
            $channelProduct->recalculateStock();

            // Recalculate aggregated price (default: lowest price)
            $this->recalculatePrice($channelProduct);

            // Mark as needs sync if product is active
            if ($channelProduct->isActive()) {
                $channelProduct->markNeedsSync();
            }

            // Mark log as success before flush to ensure atomicity
            // (avoids syncLog stuck in PROCESSING if a second flush were to fail)
            $syncLog->markSuccess([
                'price' => $channelProduct->getPlatformPrice(),
                'stock' => $channelProduct->getStockQuantity(),
                'status' => $channelProduct->getStatus(),
                'syncStatus' => $channelProduct->getSyncStatus(),
            ]);

            $this->entityManager->flush();

            $this->logger->info('Channel product aggregation completed', [
                'channelProductId' => $channelProduct->getId(),
                'stock' => $channelProduct->getStockQuantity(),
                'price' => $channelProduct->getPlatformPrice(),
            ]);

            return $syncLog;
        } catch (\Throwable $e) {
            $channelProduct->markSyncFailed($e->getMessage());
            $syncLog->markFailed($e->getMessage());

            try {
                $this->entityManager->flush();
            } catch (\Throwable $flushException) {
                $this->logger->error('Failed to persist error state during aggregation', [
                    'channelProductId' => $channelProduct->getId(),
                    'originalError' => $e->getMessage(),
                    'flushError' => $flushException->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Push channel product to external channel.
     *
     * Called by PushChannelProductMessageHandler.
     */
    public function pushToChannel(
        ChannelProduct $channelProduct,
        string $operation,
        bool $forceFullSync = false,
    ): ChannelProductSyncLog {
        // Create sync log
        $syncLog = ChannelProductSyncLog::createForPush($channelProduct, $operation);
        $syncLog->markProcessing();

        $this->entityManager->persist($syncLog);

        try {
            $salesChannel = $channelProduct->getSalesChannel();

            // Check if gateway exists
            if (!$this->gatewayRegistry->has($salesChannel->getCode())) {
                $errorMessage = sprintf('No gateway for channel: %s', $salesChannel->getCode());
                $channelProduct->markSyncFailed($errorMessage);
                $syncLog->markSkipped($errorMessage);
                $this->entityManager->flush();

                return $syncLog;
            }

            // Create gateway context
            $context = new ChannelGatewayContext($salesChannel);
            $gateway = $this->gatewayRegistry->get($salesChannel->getCode());

            // Perform push based on operation
            $response = match ($operation) {
                ChannelProductSyncLog::OPERATION_PUSH_PRODUCT => $this->doPushProduct($gateway, $context, $channelProduct),
                ChannelProductSyncLog::OPERATION_UPDATE_STOCK_PRICE => $this->doUpdateStockPrice($gateway, $context, $channelProduct),
                ChannelProductSyncLog::OPERATION_DELIST => $this->doDelistProduct($gateway, $context, $channelProduct),
                default => throw new \InvalidArgumentException(sprintf('Unknown operation: %s', $operation)),
            };

            // Handle response
            $this->handleResponse($operation, $response, $channelProduct, $syncLog);

            // Allow gateway to store channel-specific extra data after successful sync
            if ($response['success']) {
                $gateway->onAfterSync($operation, $channelProduct, $response);
            }

            $this->entityManager->flush();

            return $syncLog;
        } catch (ChannelGatewayException $e) {
            $channelProduct->markSyncFailed($e->getMessage());
            $syncLog->markFailed($e->getMessage(), $e->getErrorCode());
            throw $e;
        } catch (\Throwable $e) {
            $channelProduct->markSyncFailed($e->getMessage());
            $syncLog->markFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * @param string $operation
     * @param array $response
     * @param ChannelProduct $channelProduct
     * @param ChannelProductSyncLog $syncLog
     * @return void
     */
    private function handleResponse(string $operation,array $response, ChannelProduct $channelProduct, ChannelProductSyncLog $syncLog): void
    {
        if ($response['success']) {
            match ($operation) {
                ChannelProductSyncLog::OPERATION_PUSH_PRODUCT => $this->handlePushProductResponse($response, $channelProduct, $syncLog),
                ChannelProductSyncLog::OPERATION_UPDATE_STOCK_PRICE => $this->handleUpdateStockPriceResponse($response, $channelProduct, $syncLog),
                ChannelProductSyncLog::OPERATION_DELIST => $this->handleDelistResponse($response, $channelProduct, $syncLog),
                default => throw new \InvalidArgumentException(sprintf('Unknown operation: %s', $operation)),
            };
        } else {
            $channelProduct->markSyncFailed($response['message'] ?? 'Unknown error');
            $syncLog->markFailed(
                $response['message'] ?? 'Push failed',
                $response['errorCode'] ?? null,
                $response['data'] ?? null,
            );
        }
    }

    private function handleDelistResponse(array $response, ChannelProduct $channelProduct, ChannelProductSyncLog $syncLog)
    {
        $channelProduct->markDelisted();

        $syncLog->markSuccess([
            'status' => $channelProduct->getStatus(),
            'syncStatus' => $channelProduct->getSyncStatus(),
        ]);
        $syncLog->setExternalResponse($response['data'] ?? null);
    }

    private function handleUpdateStockPriceResponse(array $response, ChannelProduct $channelProduct, ChannelProductSyncLog $syncLog): void
    {
        foreach ($response['data'] ?? [] as $channelProductId => $responseDatum) {
            if ($channelProductId === $channelProduct->getId()) {
                if (!isset($responseDatum['externalId'])) {
                    continue;
                }
                $channelProduct->setExternalId($responseDatum['externalId']);
            }
        }
        $channelProduct->markSynced();

        $syncLog->markSuccess([
            'price' => $channelProduct->getPlatformPrice(),
            'stock' => $channelProduct->getStockQuantity(),
            'status' => $channelProduct->getStatus(),
            'syncStatus' => $channelProduct->getSyncStatus(),
            'externalId' => $channelProduct->getExternalId(),
        ]);
        $syncLog->setExternalResponse($response['data'] ?? null);

    }

    private function handlePushProductResponse(array $response, ChannelProduct $channelProduct, ChannelProductSyncLog $syncLog)
    {
        // Update channel product
        if (isset($response['externalId'])) {
            $channelProduct->setExternalId($response['externalId']);
        }
        if (isset($response['externalUrl'])) {
            $channelProduct->setExternalUrl($response['externalUrl']);
        }
        $channelProduct->markSynced();

        $syncLog->markSuccess([
            'price' => $channelProduct->getPlatformPrice(),
            'stock' => $channelProduct->getStockQuantity(),
            'status' => $channelProduct->getStatus(),
            'syncStatus' => $channelProduct->getSyncStatus(),
            'externalId' => $channelProduct->getExternalId(),
        ]);
        $syncLog->setExternalResponse($response['data'] ?? null);
    }

    /**
     * Find or create channel product for a listing.
     */
    private function findOrCreateChannelProduct(InventoryListing $listing): ?ChannelProduct
    {
        $merchantChannel = $listing->getMerchantSalesChannel();
        if ($merchantChannel === null) {
            return null;
        }

        $salesChannel = $merchantChannel->getSalesChannel();
        $productSku = $listing->getMerchantInventory()->getProductSku();

        // Try to find existing
        $channelProduct = $this->channelProductRepo->findOneByChannelAndSku($salesChannel, $productSku);

        $needsFlush = false;
        if ($channelProduct === null) {
            // Create new
            $channelProduct = new ChannelProduct();
            $channelProduct->setId($this->businessNoGenerator->generateChannelProductId());
            $channelProduct->setSalesChannel($salesChannel);
            $channelProduct->setProductSku($productSku);
            $channelProduct->setPlatformPrice($listing->getPrice());
            $channelProduct->setStatus(ChannelProduct::STATUS_DRAFT);
            $this->entityManager->persist($channelProduct);
            $needsFlush = true;
        }

        if ($salesChannel->getConfigValue('autoPush', false) && $listing->getStatus() == InventoryListing::STATUS_ACTIVE) {
            $channelProduct->setStatus(ChannelProduct::STATUS_ACTIVE);
            $needsFlush = true;
        }

        if ($needsFlush) {
            $this->entityManager->flush();
        }

        return $channelProduct;
    }

    /**
     * Find channel product for a listing.
     */
    private function findChannelProductForListing(InventoryListing $listing): ?ChannelProduct
    {
        $merchantChannel = $listing->getMerchantSalesChannel();
        if ($merchantChannel === null) {
            return null;
        }

        $salesChannel = $merchantChannel->getSalesChannel();
        $productSku = $listing->getMerchantInventory()->getProductSku();

        return $this->channelProductRepo->findOneByChannelAndSku($salesChannel, $productSku);
    }

    /**
     * Ensure source link exists between channel product and listing.
     * Also updates the source's isActive status to match listing's actual status.
     */
    private function ensureSourceExists(
        ChannelProduct $channelProduct,
        InventoryListing $listing
    ): void {
        $source = $this->sourceRepo->findOneByProductAndListing($channelProduct, $listing);
        $shouldBeActive = $listing->getStatus() === InventoryListing::STATUS_ACTIVE;

        if ($source === null) {
            $source = new ChannelProductSource();
            $source->setId($this->businessNoGenerator->generateChannelProductSourceId());
            $source->setChannelProduct($channelProduct);
            $source->setInventoryListing($listing);
            $source->setPriority(0);
            $source->setIsActive($shouldBeActive);

            $this->entityManager->persist($source);
            $this->entityManager->flush();
        } elseif ($source->isActive() !== $shouldBeActive) {
            // Update existing source's isActive status if it doesn't match listing status
            $source->setIsActive($shouldBeActive);
            $this->entityManager->flush();

            $this->logger->info('Source isActive updated in ensureSourceExists', [
                'sourceId' => $source->getId(),
                'listingId' => $listing->getId(),
                'listingStatus' => $listing->getStatus(),
                'newIsActive' => $shouldBeActive,
            ]);
        }
    }

    /**
     * Sync all sources for a channel product based on actual listing status.
     *
     * Used for compensation/repair scenarios to fix historical data.
     *
     * @return int Number of sources that were corrected
     */
    public function syncAllSourceStatuses(ChannelProduct $channelProduct): int
    {
        $correctedCount = 0;

        foreach ($channelProduct->getSources() as $source) {
            $listing = $source->getInventoryListing();
            $shouldBeActive = $listing->getStatus() === InventoryListing::STATUS_ACTIVE;

            if ($source->isActive() !== $shouldBeActive) {
                $source->setIsActive($shouldBeActive);
                ++$correctedCount;

                $this->logger->info('Source status corrected in batch sync', [
                    'sourceId' => $source->getId(),
                    'listingId' => $listing->getId(),
                    'listingStatus' => $listing->getStatus(),
                    'newIsActive' => $shouldBeActive,
                ]);
            }
        }

        if ($correctedCount > 0) {
            $this->entityManager->flush();
        }

        return $correctedCount;
    }

    /**
     * Sync source status based on listing's actual status.
     *
     * This ensures self-healing when previous sync failed.
     * Instead of relying on trigger source, we always check the actual listing status.
     */
    private function syncSourceStatus(
        ChannelProduct $channelProduct,
        InventoryListing $listing,
    ): void {
        $source = $this->sourceRepo->findOneByProductAndListing($channelProduct, $listing);

        if ($source === null) {
            return;
        }

        // Always sync based on listing's actual status
        $shouldBeActive = $listing->getStatus() === InventoryListing::STATUS_ACTIVE;

        if ($source->isActive() !== $shouldBeActive) {
            $source->setIsActive($shouldBeActive);
            $this->logger->info('Source status corrected', [
                'sourceId' => $source->getId(),
                'listingId' => $listing->getId(),
                'listingStatus' => $listing->getStatus(),
                'newIsActive' => $shouldBeActive,
            ]);
        }
    }

    /**
     * Recalculate aggregated price (default: lowest price strategy).
     */
    private function recalculatePrice(ChannelProduct $channelProduct): void
    {
        $activeSources = $channelProduct->getActiveSources();

        if ($activeSources->isEmpty()) {
            $channelProduct->setPlatformPrice('0.00');

            return;
        }

        // Default strategy: lowest price
        $lowestPrice = null;
        foreach ($activeSources as $source) {
            $price = $source->getInventoryListing()->getPrice();
            if ($lowestPrice === null || bccomp($price, $lowestPrice, 2) < 0) {
                $lowestPrice = $price;
            }
        }

        if ($lowestPrice !== null) {
            $channelProduct->setPlatformPrice($lowestPrice);
        }
    }

    /**
     * Dispatch sync message with delay (trailing debounce).
     *
     * 使用延迟消息实现尾部防抖：
     * - 每次变动派发一个延迟 N 秒的消息，并记录时间戳
     * - 消息处理器检查时间戳，只处理最新的请求
     * - 这样可以确保所有变动都被正确处理，同时避免频繁同步
     */
    private function dispatchWithDebounce(
        ChannelProduct $channelProduct,
        SyncTriggerSourceEnum $triggerSource,
        ?string $inventoryListingId,
        ?string $merchantId,
        ?string $merchantInventoryId,
    ): void {
        $timestampKey = sprintf('sync:timestamp:%s', $channelProduct->getId());
        $currentTimestamp = (string) microtime(true);

        // 先派发延迟消息，确保消息成功入队后再更新时间戳
        $message = SyncChannelProductMessage::create(
            $channelProduct->getId(),
            $triggerSource,
            $inventoryListingId,
            $merchantId,
            $merchantInventoryId,
            $currentTimestamp,
        );

        $this->messageBus->dispatch(
            $message,
            [new DelayStamp(self::DEBOUNCE_TTL_SECONDS * 1000)]  // 毫秒
        );

        // 消息派发成功后再记录时间戳（原子操作，防止 key 堆积）
        $this->redis->setex($timestampKey, self::DEBOUNCE_TTL_SECONDS + 10, $currentTimestamp);

        $this->logger->info('Dispatched delayed sync message', [
            'channelProductId' => $channelProduct->getId(),
            'triggerSource' => $triggerSource->value,
            'timestamp' => $currentTimestamp,
            'delaySeconds' => self::DEBOUNCE_TTL_SECONDS,
        ]);
    }

    /**
     * 检查消息是否应该被处理（是否是最新的请求）.
     */
    public function shouldProcessMessage(SyncChannelProductMessage $message): bool
    {
        $timestampKey = sprintf('sync:timestamp:%s', $message->channelProductId);
        $latestTimestamp = $this->redis->get($timestampKey);

        // 如果没有记录的时间戳，或者消息的时间戳是最新的，则处理
        if ($latestTimestamp === null || $message->getDispatchTimestamp() === $latestTimestamp) {
            return true;
        }

        $this->logger->info('Skipping outdated sync message', [
            'channelProductId' => $message->channelProductId,
            'messageTimestamp' => $message->getDispatchTimestamp(),
            'latestTimestamp' => $latestTimestamp,
        ]);

        return false;
    }

    /**
     * Push new product to external channel.
     *
     * @return array{success: bool, externalId?: string, externalUrl?: string, message?: string, errorCode?: string, data?: array}
     */
    private function doPushProduct(ChannelGatewayInterface $gateway, ChannelGatewayContext $context, ChannelProduct $channelProduct): array
    {
        $sku = $channelProduct->getProductSku();
        $product = $sku->getProduct();

        // Build SKU DTO with actual data
        $skuDto = new ProductSkuDto(
            sizeValue: $sku->getSizeValue() ?? '',
            sizeUnit: $sku->getSizeUnit()?->value ?? 'US',
            price: $channelProduct->getPlatformPrice(),
            compareAtPrice: $channelProduct->getPlatformCompareAtPrice(),
            stock: $channelProduct->getEffectiveStock()
        );

        // Build images array
        $images = [];
        $primaryImage = $product->getPrimaryImage();
        if ($primaryImage !== null) {
            $images[] = new ProductImageDto(
                url: $primaryImage->getUrl(),
                isPrimary: true,
                sortOrder: 0,
            );
        }

        $request = new PushProductRequest(
            internalId: $channelProduct->getId(),
            externalId: $channelProduct->getExternalId(),
            styleNumber: $product->getStyleNumber(),
            title: $product->getName(),
            description: $product->getDescription() ?? '',
            brand: $product->getBrand()?->getName() ?? '',
            categoryCode: $product->getCategory()?->getSlug() ?? null,
            images: $images,
            skus: [$skuDto],
            currency: $sku->getCurrency(),
            attributes: [
                'model_no' => $product->getStyleNumber(),
                'size_system' => $sku->getSizeUnit() !== null ? $sku->getSizeUnit()->value : 'US',
            ],
        );
        if ($request->getTotalStock() <= 0 || $request->getPrice() <= 0) {
            return [
                'success' => false,
                'message' => 'Stock must be greater than 0',
            ];
        }

        $response = $gateway->pushProduct($context, $request);

        return [
            'success' => $response->success,
            'externalId' => $response->externalId,
            'externalUrl' => $response->externalUrl ?? null,
            'message' => $response->message,
            'data' => $response->data ?? [],
        ];
    }

    /**
     * Update stock and price on external channel.
     *
     * @return array{success: bool, message?: string, errorCode?: string, data?: array}
     */
    private function doUpdateStockPrice(ChannelGatewayInterface $gateway, ChannelGatewayContext $context, ChannelProduct $channelProduct): array
    {
        $response = $gateway->updateStockPrice($context, [$channelProduct]);

        return [
            'success' => $response->success,
            'message' => $response->message,
            'data' => $response->data ?? [],
        ];
    }

    /**
     * Delist (remove) product from external channel.
     *
     * @return array{success: bool, message?: string, errorCode?: string, data?: array}
     */
    private function doDelistProduct(ChannelGatewayInterface $gateway, ChannelGatewayContext $context, ChannelProduct $channelProduct): array
    {
        $response = $gateway->delistProduct($context, $channelProduct);

        return [
            'success' => $response->success,
            'message' => $response->message,
            'data' => $response->data ?? [],
        ];
    }
}
