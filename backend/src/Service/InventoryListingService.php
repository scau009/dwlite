<?php

namespace App\Service;

use App\Dto\Merchant\CreateListingRequest;
use App\Dto\Merchant\UpdateListingRequest;
use App\Entity\InventoryListing;
use App\Entity\Merchant;
use App\Entity\MerchantInventory;
use App\Entity\MerchantSalesChannel;
use App\Entity\User;
use App\Enum\SyncTriggerSource;
use App\Repository\InventoryListingRepository;
use App\Repository\MerchantInventoryRepository;
use App\Repository\MerchantSalesChannelRepository;
use App\Repository\SalesChannelWarehouseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class InventoryListingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InventoryListingRepository $listingRepository,
        private MerchantInventoryRepository $inventoryRepository,
        private MerchantSalesChannelRepository $channelRepository,
        private SalesChannelWarehouseRepository $salesChannelWarehouseRepository,
        private TranslatorInterface $translator,
        private ListingOperationLogService $logService,
        private ChannelProductSyncService $syncService,
    ) {
    }

    /**
     * Create a new inventory listing.
     */
    public function createListing(Merchant $merchant, CreateListingRequest $request, User $operator): InventoryListing
    {
        // 1. Validate merchantInventory exists and belongs to merchant
        $inventory = $this->inventoryRepository->find($request->merchantInventoryId);
        if (!$inventory || $inventory->getMerchant()->getId() !== $merchant->getId()) {
            throw new \InvalidArgumentException($this->translator->trans('listing.inventoryNotFound', [], 'messages'));
        }

        // 2. Validate merchantSalesChannel exists, belongs to merchant and is active
        $channel = $this->channelRepository->find($request->merchantSalesChannelId);
        if (!$channel || $channel->getMerchant()->getId() !== $merchant->getId()) {
            throw new \InvalidArgumentException($this->translator->trans('listing.channelNotFound', [], 'messages'));
        }

        if (!$channel->isActive()) {
            throw new \InvalidArgumentException($this->translator->trans('listing.channelNotActive', [], 'messages'));
        }

        // 3. Validate fulfillmentType is in channel's approvedFulfillmentTypes
        if (!$channel->hasApprovedFulfillmentType($request->fulfillmentType)) {
            throw new \InvalidArgumentException($this->translator->trans('listing.fulfillmentTypeNotApproved', [], 'messages'));
        }

        // 4. For consignment mode: validate inventory is in a platform warehouse configured for the channel
        if ($request->fulfillmentType === InventoryListing::FULFILLMENT_CONSIGNMENT) {
            $this->validateConsignmentInventory($inventory, $channel);
        }

        // 5. Check stock > 0
        if ($inventory->getQuantityAvailable() <= 0) {
            throw new \InvalidArgumentException($this->translator->trans('listing.noAvailableStock', [], 'messages'));
        }

        // 6. Check no existing listing for same inventory+channel
        $existingListing = $this->listingRepository->findOneByInventoryAndChannel($inventory, $channel);
        if ($existingListing !== null) {
            throw new \InvalidArgumentException($this->translator->trans('listing.alreadyExists', [], 'messages'));
        }

        // 7. Validate dedicated allocation quantity
        if ($request->allocationMode === InventoryListing::MODE_DEDICATED) {
            if ($request->allocatedQuantity === null || $request->allocatedQuantity <= 0) {
                throw new \InvalidArgumentException($this->translator->trans('listing.allocatedQuantityRequired', [], 'messages'));
            }

            // Check if there's enough shareable inventory
            $shareableQuantity = $inventory->getShareableQuantity();
            if ($request->allocatedQuantity > $shareableQuantity) {
                throw new \InvalidArgumentException($this->translator->trans('listing.insufficientShareableStock', ['%available%' => $shareableQuantity, '%requested%' => $request->allocatedQuantity], 'messages'));
            }
        }

        // Create the listing
        $listing = new InventoryListing();
        $listing->setMerchantInventory($inventory);
        $listing->setMerchantSalesChannel($channel);
        $listing->setFulfillmentType($request->fulfillmentType);
        $listing->setPricingModel($request->pricingModel);
        $listing->setPrice($request->price);
        $listing->setCompareAtPrice($request->compareAtPrice);
        $listing->setRemark($request->remark);
        $listing->setPriceRuleExpression($request->priceRuleExpression);
        $listing->setStockRuleExpression($request->stockRuleExpression);

        // Set allocation mode and quantity
        if ($request->allocationMode === InventoryListing::MODE_DEDICATED) {
            $listing->setDedicatedAllocation($request->allocatedQuantity);
        } else {
            $listing->setAllocationMode(InventoryListing::MODE_SHARED);
        }

        $this->entityManager->persist($listing);
        $this->entityManager->flush();

        // Log the create operation
        $this->logService->logCreate($listing, $operator);

        // Trigger channel product sync
        $this->syncService->triggerSyncFromListing($listing, SyncTriggerSource::LISTING_CREATE);

        return $listing;
    }

    /**
     * Update an existing listing (only price, compareAtPrice, allocatedQuantity can be changed).
     */
    public function updateListing(InventoryListing $listing, UpdateListingRequest $request, User $operator): InventoryListing
    {
        // Capture before values for logging
        $beforePrice = $listing->getPrice();
        $beforeComparePrice = $listing->getCompareAtPrice();
        $beforeRemark = $listing->getRemark();
        $beforeAllocation = [
            'allocationMode' => $listing->getAllocationMode(),
            'allocatedQuantity' => $listing->getAllocatedQuantity(),
        ];
        $listing->setPrice($request->price);
        $listing->setCompareAtPrice($request->compareAtPrice);
        $listing->setRemark($request->remark);
        $listing->setPriceRuleExpression($request->priceRuleExpression);
        $listing->setStockRuleExpression($request->stockRuleExpression);

        $inventory = $listing->getMerchantInventory();

        // Handle allocation mode change
        if ($request->allocationMode !== null && $request->allocationMode !== $listing->getAllocationMode()) {
            if ($request->allocationMode === InventoryListing::MODE_DEDICATED) {
                // Shared → Dedicated: need to allocate quantity
                if ($request->allocatedQuantity === null || $request->allocatedQuantity <= 0) {
                    throw new \InvalidArgumentException($this->translator->trans('listing.allocatedQuantityRequired', [], 'messages'));
                }

                $shareableQuantity = $inventory->getShareableQuantity();
                if ($request->allocatedQuantity > $shareableQuantity) {
                    throw new \InvalidArgumentException($this->translator->trans('listing.insufficientShareableStock', ['%available%' => $shareableQuantity, '%requested%' => $request->allocatedQuantity], 'messages'));
                }

                $listing->setDedicatedAllocation($request->allocatedQuantity);
            } else {
                // Dedicated → Shared: release allocation
                $listing->setSharedAllocation();
            }
        } elseif ($listing->isDedicated() && $request->allocatedQuantity !== null) {
            // Same mode (dedicated): adjust quantity if provided
            $currentAllocation = $listing->getAllocatedQuantity() ?? 0;

            // Calculate max possible allocation
            $shareableQuantity = $inventory->getShareableQuantity() + $currentAllocation;

            if ($request->allocatedQuantity > $shareableQuantity) {
                throw new \InvalidArgumentException($this->translator->trans('listing.insufficientShareableStock', ['%available%' => $shareableQuantity, '%requested%' => $request->allocatedQuantity], 'messages'));
            }

            if ($request->allocatedQuantity <= 0) {
                throw new \InvalidArgumentException($this->translator->trans('listing.allocatedQuantityRequired', [], 'messages'));
            }

            $listing->adjustAllocatedQuantity($request->allocatedQuantity);
        }

        $this->entityManager->flush();

        // Log changes
        $afterPrice = $listing->getPrice();
        $afterComparePrice = $listing->getCompareAtPrice();
        $afterRemark = $listing->getRemark();
        $afterAllocation = [
            'allocationMode' => $listing->getAllocationMode(),
            'allocatedQuantity' => $listing->getAllocatedQuantity(),
        ];

        if ($beforePrice !== $afterPrice) {
            $this->logService->logPriceUpdate($listing, $operator, $beforePrice, $afterPrice);
        }
        if ($beforeComparePrice !== $afterComparePrice) {
            $this->logService->logComparePriceUpdate($listing, $operator, $beforeComparePrice, $afterComparePrice);
        }
        if ($beforeRemark !== $afterRemark) {
            $this->logService->logRemarkUpdate($listing, $operator, $beforeRemark, $afterRemark);
        }
        if ($beforeAllocation !== $afterAllocation) {
            $this->logService->logAllocationUpdate($listing, $operator, $beforeAllocation, $afterAllocation);
        }

        // Trigger channel product sync if price or allocation changed
        if ($beforePrice !== $afterPrice || $beforeAllocation !== $afterAllocation) {
            $this->syncService->triggerSyncFromListing($listing, SyncTriggerSource::LISTING_UPDATE);
        }

        return $listing;
    }

    /**
     * Activate a listing.
     */
    public function activateListing(InventoryListing $listing, User $operator): void
    {
        if (!$listing->hasAvailableStock()) {
            throw new \InvalidArgumentException($this->translator->trans('listing.noAvailableStock', [], 'messages'));
        }

        $listing->activate();
        $this->entityManager->flush();

        $this->logService->logActivate($listing, $operator);

        // Trigger channel product sync
        $this->syncService->triggerSyncFromListing($listing, SyncTriggerSource::LISTING_ACTIVATE);
    }

    /**
     * Pause a listing.
     */
    public function pauseListing(InventoryListing $listing, User $operator): void
    {
        $previousStatus = $listing->getStatus();
        $listing->pause();
        $this->entityManager->flush();

        $this->logService->logPause($listing, $operator, $previousStatus);

        // Trigger channel product sync
        $this->syncService->triggerSyncFromListing($listing, SyncTriggerSource::LISTING_PAUSE);
    }

    /**
     * Delete a listing (only draft status).
     */
    public function deleteListing(InventoryListing $listing, User $operator): void
    {
        if (!$listing->isDraft()) {
            throw new \InvalidArgumentException($this->translator->trans('listing.canOnlyDeleteDraft', [], 'messages'));
        }

        // Capture data for sync trigger before deletion
        $merchantId = $listing->getMerchant()->getId();

        // Log the delete operation before actually deleting
        $this->logService->logDelete($listing, $operator);

        // If it was dedicated mode, release the allocation
        if ($listing->isDedicated() && $listing->getAllocatedQuantity() !== null) {
            $listing->setSharedAllocation();
        }

        // Trigger channel product sync before deletion (so the sync service can find the ChannelProduct)
        $this->syncService->triggerSyncFromListing($listing, SyncTriggerSource::LISTING_DELETE);

        $this->entityManager->remove($listing);
        $this->entityManager->flush();
    }

    /**
     * Validate that inventory can be used for consignment mode on the given channel.
     */
    private function validateConsignmentInventory(MerchantInventory $inventory, MerchantSalesChannel $channel): void
    {
        $warehouse = $inventory->getWarehouse();

        // 1. Check if inventory is in a platform warehouse
        if (!$warehouse->isPlatformWarehouse()) {
            throw new \InvalidArgumentException($this->translator->trans('listing.consignmentRequiresPlatformWarehouse', [], 'messages'));
        }

        // 2. Check if the warehouse is configured for this channel
        $salesChannel = $channel->getSalesChannel();
        $isConfigured = $this->salesChannelWarehouseRepository->hasWarehouse($salesChannel, $warehouse);

        if (!$isConfigured) {
            throw new \InvalidArgumentException($this->translator->trans('listing.warehouseNotConfiguredForChannel', [], 'messages'));
        }
    }

    /**
     * Get inventory available for listing on a specific channel.
     *
     * @return MerchantInventory[]
     */
    public function getAvailableInventoryForChannel(
        Merchant $merchant,
        MerchantSalesChannel $channel,
        int $page = 1,
        int $limit = 20,
        ?string $search = null
    ): array {
        return $this->inventoryRepository->findAvailableForListing(
            $merchant,
            $channel,
            $page,
            $limit,
            $search
        );
    }

    /**
     * Count inventory available for listing.
     */
    public function countAvailableInventoryForChannel(
        Merchant $merchant,
        MerchantSalesChannel $channel,
        ?string $search = null
    ): int {
        return $this->inventoryRepository->countAvailableForListing(
            $merchant,
            $channel,
            $search
        );
    }
}
