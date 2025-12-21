<?php

namespace App\Dto\Admin\Query;

class ProductListQuery extends PaginationQuery
{
    public ?string $search = null;

    public ?string $brandId = null;

    public ?string $categoryId = null;

    public ?string $season = null;

    public ?string $status = null;

    public ?bool $isActive = null;

    public ?string $sortBy = null;

    public ?string $sortOrder = null;

    public function toFilters(): array
    {
        $filters = [];

        if ($this->search !== null && $this->search !== '') {
            $filters['search'] = $this->search;
        }

        if ($this->brandId !== null && $this->brandId !== '') {
            $filters['brandId'] = $this->brandId;
        }

        if ($this->categoryId !== null && $this->categoryId !== '') {
            $filters['categoryId'] = $this->categoryId;
        }

        if ($this->season !== null && $this->season !== '') {
            $filters['season'] = $this->season;
        }

        if ($this->status !== null && $this->status !== '') {
            $filters['status'] = $this->status;
        }

        if ($this->isActive !== null) {
            $filters['isActive'] = $this->isActive;
        }

        if ($this->sortBy !== null && $this->sortBy !== '') {
            $filters['sortBy'] = $this->sortBy;
        }

        if ($this->sortOrder !== null && $this->sortOrder !== '') {
            $filters['sortOrder'] = $this->sortOrder;
        }

        return $filters;
    }
}
