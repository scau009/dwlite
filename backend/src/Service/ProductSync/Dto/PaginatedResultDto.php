<?php

namespace App\Service\ProductSync\Dto;

/**
 * Data Transfer Object for paginated API results.
 */
readonly class PaginatedResultDto
{
    /**
     * @param ExternalProductDto[] $products    Array of products on this page
     * @param int                  $totalCount  Total number of products available
     * @param int                  $pageNumber  Current page number (1-based)
     * @param int                  $pageSize    Number of products per page
     * @param bool                 $hasNextPage Whether there are more pages
     * @param int|null             $lastRank    Last product's rank for cursor-based pagination
     */
    public function __construct(
        public array $products,
        public int $totalCount,
        public int $pageNumber,
        public int $pageSize,
        public bool $hasNextPage,
        public ?int $lastRank = null,
    ) {
    }

    /**
     * Calculate total number of pages.
     */
    public function getTotalPages(): int
    {
        if ($this->pageSize <= 0) {
            return 0;
        }

        return (int) ceil($this->totalCount / $this->pageSize);
    }

    /**
     * Check if this is the first page.
     */
    public function isFirstPage(): bool
    {
        return $this->pageNumber === 1;
    }

    /**
     * Check if this is the last page.
     */
    public function isLastPage(): bool
    {
        return !$this->hasNextPage;
    }

    /**
     * Get the number of products on this page.
     */
    public function getProductCount(): int
    {
        return count($this->products);
    }
}
