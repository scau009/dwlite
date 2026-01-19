<?php

namespace App\Dto\Admin\Query;

class ChannelProductListQuery extends PaginationQuery
{
    public ?string $salesChannelId = null;

    public ?string $status = null;

    public ?string $syncStatus = null;

    public ?string $search = null;

    public function toFilters(): array
    {
        $filters = [];

        if ($this->salesChannelId !== null && $this->salesChannelId !== '') {
            $filters['salesChannelId'] = $this->salesChannelId;
        }

        if ($this->status !== null && $this->status !== '') {
            $filters['status'] = $this->status;
        }

        if ($this->syncStatus !== null && $this->syncStatus !== '') {
            $filters['syncStatus'] = $this->syncStatus;
        }

        if ($this->search !== null && $this->search !== '') {
            $filters['search'] = $this->search;
        }

        return $filters;
    }
}
