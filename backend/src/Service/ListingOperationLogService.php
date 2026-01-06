<?php

namespace App\Service;

use App\Entity\InventoryListing;
use App\Entity\ListingOperationLog;
use App\Entity\User;
use App\Repository\ListingOperationLogRepository;
use Doctrine\ORM\EntityManagerInterface;

class ListingOperationLogService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ListingOperationLogRepository $logRepository,
    ) {
    }

    /**
     * 记录创建操作.
     */
    public function logCreate(InventoryListing $listing, User $operator): void
    {
        $log = $this->createLog($listing, $operator, ListingOperationLog::OPERATION_CREATE);
        $log->setChanges([
            'after' => $this->extractListingData($listing),
        ]);

        $this->save($log);
    }

    /**
     * 记录更新操作.
     *
     * @param array<string, mixed> $beforeData 更新前的数据
     * @param array<string, mixed> $afterData  更新后的数据
     */
    public function logUpdate(
        InventoryListing $listing,
        User $operator,
        string $operation,
        array $beforeData,
        array $afterData
    ): void {
        $log = $this->createLog($listing, $operator, $operation);
        $log->setChanges([
            'before' => $beforeData,
            'after' => $afterData,
        ]);

        $this->save($log);
    }

    /**
     * 记录价格更新.
     */
    public function logPriceUpdate(
        InventoryListing $listing,
        User $operator,
        string $oldPrice,
        string $newPrice
    ): void {
        $this->logUpdate(
            $listing,
            $operator,
            ListingOperationLog::OPERATION_UPDATE_PRICE,
            ['price' => $oldPrice],
            ['price' => $newPrice]
        );
    }

    /**
     * 记录比较价更新.
     */
    public function logComparePriceUpdate(
        InventoryListing $listing,
        User $operator,
        ?string $oldPrice,
        ?string $newPrice
    ): void {
        $this->logUpdate(
            $listing,
            $operator,
            ListingOperationLog::OPERATION_UPDATE_COMPARE_PRICE,
            ['compareAtPrice' => $oldPrice],
            ['compareAtPrice' => $newPrice]
        );
    }

    /**
     * 记录库存分配更新.
     */
    public function logAllocationUpdate(
        InventoryListing $listing,
        User $operator,
        array $oldAllocation,
        array $newAllocation
    ): void {
        $this->logUpdate(
            $listing,
            $operator,
            ListingOperationLog::OPERATION_UPDATE_ALLOCATION,
            $oldAllocation,
            $newAllocation
        );
    }

    /**
     * 记录备注更新.
     */
    public function logRemarkUpdate(
        InventoryListing $listing,
        User $operator,
        ?string $oldRemark,
        ?string $newRemark
    ): void {
        $this->logUpdate(
            $listing,
            $operator,
            ListingOperationLog::OPERATION_UPDATE_REMARK,
            ['remark' => $oldRemark],
            ['remark' => $newRemark]
        );
    }

    /**
     * 记录激活操作.
     */
    public function logActivate(InventoryListing $listing, User $operator): void
    {
        $log = $this->createLog($listing, $operator, ListingOperationLog::OPERATION_ACTIVATE);
        $log->setChanges([
            'before' => ['status' => InventoryListing::STATUS_DRAFT],
            'after' => ['status' => InventoryListing::STATUS_ACTIVE],
        ]);

        $this->save($log);
    }

    /**
     * 记录暂停操作.
     */
    public function logPause(InventoryListing $listing, User $operator, string $previousStatus): void
    {
        $log = $this->createLog($listing, $operator, ListingOperationLog::OPERATION_PAUSE);
        $log->setChanges([
            'before' => ['status' => $previousStatus],
            'after' => ['status' => InventoryListing::STATUS_PAUSED],
        ]);

        $this->save($log);
    }

    /**
     * 记录删除操作.
     */
    public function logDelete(InventoryListing $listing, User $operator): void
    {
        $log = $this->createLog($listing, $operator, ListingOperationLog::OPERATION_DELETE);
        $log->setChanges([
            'before' => $this->extractListingData($listing),
        ]);

        $this->save($log);
    }

    /**
     * 获取上架的操作日志.
     *
     * @return ListingOperationLog[]
     */
    public function getListingLogs(string $listingId, int $limit = 50): array
    {
        return $this->logRepository->findByListing($listingId, $limit);
    }

    /**
     * 获取商户的操作日志（分页）.
     *
     * @return array{data: ListingOperationLog[], total: int}
     */
    public function getMerchantLogs(
        string $merchantId,
        int $page = 1,
        int $limit = 20,
        array $filters = []
    ): array {
        return $this->logRepository->findByMerchantPaginated($merchantId, $page, $limit, $filters);
    }

    private function createLog(InventoryListing $listing, User $operator, string $operation): ListingOperationLog
    {
        $log = new ListingOperationLog();
        $log->setListingId($listing->getId());
        $log->setMerchantId($listing->getMerchantInventory()->getMerchant()->getId());
        $log->setOperatorId($operator->getId());
        $log->setOperation($operation);

        return $log;
    }

    private function save(ListingOperationLog $log): void
    {
        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    /**
     * 提取上架数据用于日志记录.
     */
    private function extractListingData(InventoryListing $listing): array
    {
        return [
            'price' => $listing->getPrice(),
            'compareAtPrice' => $listing->getCompareAtPrice(),
            'allocationMode' => $listing->getAllocationMode(),
            'allocatedQuantity' => $listing->getAllocatedQuantity(),
            'fulfillmentType' => $listing->getFulfillmentType(),
            'pricingModel' => $listing->getPricingModel(),
            'status' => $listing->getStatus(),
            'remark' => $listing->getRemark(),
        ];
    }
}
