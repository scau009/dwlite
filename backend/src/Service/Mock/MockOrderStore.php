<?php

declare(strict_types=1);

namespace App\Service\Mock;

use App\Service\Mock\Dto\MockOrderDto;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for managing mock orders in Redis cache.
 *
 * Stores mock orders that can be pulled by MockGateway for testing
 * the complete order workflow.
 */
class MockOrderStore
{
    // Cache key prefixes
    private const ORDER_PREFIX = 'mock_order:';
    private const PENDING_INDEX_PREFIX = 'mock_orders_pending:';

    // Default TTL: 24 hours
    private const DEFAULT_TTL = 86400;

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Store a mock order.
     */
    public function store(string $channelId, MockOrderDto $mockOrder): void
    {
        // Store the order data
        $orderKey = $this->getOrderKey($channelId, $mockOrder->mockOrderId);
        $item = $this->cache->getItem($orderKey);
        $item->set($mockOrder->toArray());
        $item->expiresAfter(self::DEFAULT_TTL);
        $this->cache->save($item);

        // Add to pending index if order is pending
        if ($mockOrder->status === MockOrderDto::STATUS_PENDING) {
            $this->addToPendingIndex($channelId, $mockOrder->mockOrderId);
        } else {
            $this->removeFromPendingIndex($channelId, $mockOrder->mockOrderId);
        }

        $this->logger->debug('Mock order stored', [
            'channelId' => $channelId,
            'mockOrderId' => $mockOrder->mockOrderId,
            'status' => $mockOrder->status,
        ]);
    }

    /**
     * Get a mock order by ID.
     */
    public function get(string $channelId, string $mockOrderId): ?MockOrderDto
    {
        $orderKey = $this->getOrderKey($channelId, $mockOrderId);
        $item = $this->cache->getItem($orderKey);

        if (!$item->isHit()) {
            return null;
        }

        try {
            return MockOrderDto::fromArray($item->get());
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to deserialize mock order', [
                'channelId' => $channelId,
                'mockOrderId' => $mockOrderId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Delete a mock order.
     */
    public function delete(string $channelId, string $mockOrderId): void
    {
        $orderKey = $this->getOrderKey($channelId, $mockOrderId);
        $this->cache->deleteItem($orderKey);
        $this->removeFromPendingIndex($channelId, $mockOrderId);

        $this->logger->debug('Mock order deleted', [
            'channelId' => $channelId,
            'mockOrderId' => $mockOrderId,
        ]);
    }

    /**
     * Get all pending orders for a channel.
     *
     * @return MockOrderDto[]
     */
    public function getPendingOrders(string $channelId): array
    {
        $pendingIds = $this->getPendingIndex($channelId);
        $orders = [];

        foreach ($pendingIds as $mockOrderId) {
            $order = $this->get($channelId, $mockOrderId);
            if ($order !== null && $order->status === MockOrderDto::STATUS_PENDING) {
                $orders[] = $order;
            }
        }

        return $orders;
    }

    /**
     * Get all orders for a channel (any status).
     *
     * @return MockOrderDto[]
     */
    public function getAllOrders(string $channelId, ?string $status = null): array
    {
        // Get all order IDs from the index
        $indexKey = $this->getAllOrdersIndexKey($channelId);
        $item = $this->cache->getItem($indexKey);

        if (!$item->isHit()) {
            return [];
        }

        $orderIds = $item->get();
        $orders = [];

        foreach ($orderIds as $mockOrderId) {
            $order = $this->get($channelId, $mockOrderId);
            if ($order !== null) {
                if ($status === null || $order->status === $status) {
                    $orders[] = $order;
                }
            }
        }

        return $orders;
    }

    /**
     * Update mock order status.
     */
    public function updateStatus(string $channelId, string $mockOrderId, string $newStatus): bool
    {
        $order = $this->get($channelId, $mockOrderId);
        if ($order === null) {
            return false;
        }

        $updated = $order->withStatus($newStatus);
        $this->store($channelId, $updated);

        return true;
    }

    /**
     * Link mock order to internal order ID.
     */
    public function linkToInternalOrder(string $channelId, string $mockOrderId, string $internalOrderId): bool
    {
        $order = $this->get($channelId, $mockOrderId);
        if ($order === null) {
            return false;
        }

        $updated = $order->withInternalOrderId($internalOrderId);
        $this->store($channelId, $updated);

        return true;
    }

    /**
     * Find mock order by external order ID.
     */
    public function findByExternalOrderId(string $channelId, string $externalOrderId): ?MockOrderDto
    {
        $orders = $this->getAllOrders($channelId);
        foreach ($orders as $order) {
            if ($order->orderData->externalOrderId === $externalOrderId) {
                return $order;
            }
        }

        return null;
    }

    /**
     * Get order key for Redis.
     */
    private function getOrderKey(string $channelId, string $mockOrderId): string
    {
        return self::ORDER_PREFIX.$channelId.':'.$mockOrderId;
    }

    /**
     * Get pending orders index key for Redis.
     */
    private function getPendingIndexKey(string $channelId): string
    {
        return self::PENDING_INDEX_PREFIX.$channelId;
    }

    /**
     * Get all orders index key for Redis.
     */
    private function getAllOrdersIndexKey(string $channelId): string
    {
        return 'mock_orders_all:'.$channelId;
    }

    /**
     * Get pending order IDs from index.
     *
     * @return string[]
     */
    private function getPendingIndex(string $channelId): array
    {
        $indexKey = $this->getPendingIndexKey($channelId);
        $item = $this->cache->getItem($indexKey);

        if (!$item->isHit()) {
            return [];
        }

        return $item->get() ?? [];
    }

    /**
     * Add order ID to pending index.
     */
    private function addToPendingIndex(string $channelId, string $mockOrderId): void
    {
        $indexKey = $this->getPendingIndexKey($channelId);
        $item = $this->cache->getItem($indexKey);

        $pendingIds = $item->isHit() ? $item->get() : [];
        if (!in_array($mockOrderId, $pendingIds, true)) {
            $pendingIds[] = $mockOrderId;
        }

        $item->set($pendingIds);
        $item->expiresAfter(self::DEFAULT_TTL);
        $this->cache->save($item);

        // Also add to all orders index
        $this->addToAllOrdersIndex($channelId, $mockOrderId);
    }

    /**
     * Remove order ID from pending index.
     */
    private function removeFromPendingIndex(string $channelId, string $mockOrderId): void
    {
        $indexKey = $this->getPendingIndexKey($channelId);
        $item = $this->cache->getItem($indexKey);

        if (!$item->isHit()) {
            return;
        }

        $pendingIds = $item->get();
        $pendingIds = array_values(array_filter($pendingIds, fn ($id) => $id !== $mockOrderId));

        $item->set($pendingIds);
        $item->expiresAfter(self::DEFAULT_TTL);
        $this->cache->save($item);
    }

    /**
     * Add order ID to all orders index.
     */
    private function addToAllOrdersIndex(string $channelId, string $mockOrderId): void
    {
        $indexKey = $this->getAllOrdersIndexKey($channelId);
        $item = $this->cache->getItem($indexKey);

        $orderIds = $item->isHit() ? $item->get() : [];
        if (!in_array($mockOrderId, $orderIds, true)) {
            $orderIds[] = $mockOrderId;
        }

        $item->set($orderIds);
        $item->expiresAfter(self::DEFAULT_TTL);
        $this->cache->save($item);
    }

    /**
     * Clear all mock orders for a channel.
     */
    public function clearChannel(string $channelId): int
    {
        $orders = $this->getAllOrders($channelId);
        $count = count($orders);

        foreach ($orders as $order) {
            $this->delete($channelId, $order->mockOrderId);
        }

        // Clear indexes
        $this->cache->deleteItem($this->getPendingIndexKey($channelId));
        $this->cache->deleteItem($this->getAllOrdersIndexKey($channelId));

        $this->logger->info('Cleared all mock orders for channel', [
            'channelId' => $channelId,
            'count' => $count,
        ]);

        return $count;
    }
}
