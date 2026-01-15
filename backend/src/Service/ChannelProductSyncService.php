<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ChannelProduct;
use App\Entity\ChannelProductSource;
use App\Entity\ChannelProductSyncLog;
use App\Entity\InventoryListing;
use App\Entity\MerchantInventory;
use App\Enum\SyncTriggerSource;
use App\Message\SyncChannelProductMessage;
use App\Repository\ChannelProductRepository;
use App\Repository\ChannelProductSourceRepository;
use App\Repository\ChannelProductSyncLogRepository;
use App\Repository\InventoryListingRepository;
use App\Service\ChannelGateway\ChannelGatewayContext;
use App\Service\ChannelGateway\ChannelGatewayInterface;
use App\Service\ChannelGateway\ChannelGatewayRegistry;
use App\Service\ChannelGateway\Dto\Request\ProductImageDto;
use App\Service\ChannelGateway\Dto\Request\ProductSkuDto;
use App\Service\ChannelGateway\Dto\Request\PushProductRequest;
use App\Service\ChannelGateway\Dto\Request\StockPriceUpdateDto;
use App\Service\ChannelGateway\Dto\Request\UpdateStockPriceRequest;
use App\Service\ChannelGateway\Exception\ChannelGatewayException;
use Doctrine\ORM\EntityManagerInterface;
use Predis\Client as RedisClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

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
    private const DEBOUNCE_TTL_SECONDS = 5;

    public function __construct(
        private ChannelProductRepository $channelProductRepo,
        private ChannelProductSourceRepository $sourceRepo,
        private InventoryListingRepository $listingRepo,
        private ChannelProductSyncLogRepository $syncLogRepo,
        private ChannelGatewayRegistry $gatewayRegistry,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private RedisClient $redis,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Trigger sync from inventory listing change.
     *
     * Called by InventoryListingService after create/update/activate/pause/delete.
     */
    public function triggerSyncFromListing(
        InventoryListing $listing,
        SyncTriggerSource $triggerSource,
    ): void {
        // Find or create channel product
        $channelProduct = $this->findOrCreateChannelProduct($listing);
        if ($channelProduct === null) {
            return;
        }

        // Find or create source link
        $this->ensureSourceExists($channelProduct, $listing, $triggerSource);

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
        SyncTriggerSource $triggerSource,
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
        SyncTriggerSource $triggerSource,
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
        SyncTriggerSource $triggerSource,
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
            // Sync source status based on listing's actual status (self-healing)
            if ($triggerListing !== null) {
                $this->syncSourceStatus($channelProduct, $triggerListing);
            }

            // Recalculate aggregated stock
            $channelProduct->recalculateStock();

            // Recalculate aggregated price (default: lowest price)
            $this->recalculatePrice($channelProduct);

            // Mark as needs sync if product is active
            if ($channelProduct->isActive()) {
                $channelProduct->markNeedsSync();
            }

            $this->entityManager->flush();

            // Mark log as success
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
            $this->entityManager->flush();

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
                default => throw new \InvalidArgumentException(sprintf('Unknown operation: %s', $operation)),
            };

            if ($response['success']) {
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
            } else {
                $channelProduct->markSyncFailed($response['message'] ?? 'Unknown error');
                $syncLog->markFailed(
                    $response['message'] ?? 'Push failed',
                    $response['errorCode'] ?? null,
                    $response['data'] ?? null,
                );
            }

            $this->entityManager->flush();

            return $syncLog;
        } catch (ChannelGatewayException $e) {
            $channelProduct->markSyncFailed($e->getMessage());
            $syncLog->markFailed($e->getMessage(), $e->getErrorCode());
            $this->entityManager->flush();

            throw $e;
        } catch (\Throwable $e) {
            $channelProduct->markSyncFailed($e->getMessage());
            $syncLog->markFailed($e->getMessage());
            $this->entityManager->flush();

            throw $e;
        }
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

        if ($channelProduct === null) {
            // Create new
            $channelProduct = new ChannelProduct();
            $channelProduct->setSalesChannel($salesChannel);
            $channelProduct->setProductSku($productSku);
            $channelProduct->setPlatformPrice($listing->getPrice());
            $channelProduct->setStatus(ChannelProduct::STATUS_DRAFT);

            $this->entityManager->persist($channelProduct);
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
        InventoryListing $listing,
        SyncTriggerSource $triggerSource,
    ): void {
        $source = $this->sourceRepo->findOneByProductAndListing($channelProduct, $listing);
        $shouldBeActive = $listing->getStatus() === InventoryListing::STATUS_ACTIVE;

        if ($source === null) {
            $source = new ChannelProductSource();
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
     * Dispatch sync message with debounce.
     */
    private function dispatchWithDebounce(
        ChannelProduct $channelProduct,
        SyncTriggerSource $triggerSource,
        ?string $inventoryListingId,
        ?string $merchantId,
        ?string $merchantInventoryId,
    ): void {
        $lockKey = sprintf('sync:debounce:%s', $channelProduct->getId());

        // Check debounce
        if ($this->redis->exists($lockKey)) {
            $this->logger->debug('Sync debounced', [
                'channelProductId' => $channelProduct->getId(),
            ]);

            return;
        }

        // Set debounce lock
        $this->redis->setex($lockKey, self::DEBOUNCE_TTL_SECONDS, '1');

        // Dispatch message
        $this->messageBus->dispatch(SyncChannelProductMessage::create(
            $channelProduct->getId(),
            $triggerSource,
            $inventoryListingId,
            $merchantId,
            $merchantInventoryId,
        ));

        $this->logger->info('Dispatched sync message', [
            'channelProductId' => $channelProduct->getId(),
            'triggerSource' => $triggerSource->value,
        ]);
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
            internalId: $channelProduct->getId(),
            externalId: $channelProduct->getExternalId(),
            skuCode: $product->getStyleNumber(),
            sizeValue: $sku->getSizeValue(),
            price: $channelProduct->getPlatformPrice(),
            compareAtPrice: $channelProduct->getPlatformCompareAtPrice(),
            stock: $channelProduct->getStockQuantity(),
            barcode: $sku->getBarcode(),
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
    private function doUpdateStockPrice($gateway, ChannelGatewayContext $context, ChannelProduct $channelProduct): array
    {
        $request = new UpdateStockPriceRequest([
            new StockPriceUpdateDto(
                externalId: $channelProduct->getExternalId() ?? '',
                stock: $channelProduct->getStockQuantity(),
                price: $channelProduct->getPlatformPrice(),
                compareAtPrice: $channelProduct->getPlatformCompareAtPrice(),
            ),
        ]);

        $response = $gateway->updateStockPrice($context, $request);

        return [
            'success' => $response->success,
            'message' => $response->message,
            'data' => $response->data ?? [],
        ];
    }
}
