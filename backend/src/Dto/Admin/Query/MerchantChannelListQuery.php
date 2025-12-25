<?php

namespace App\Dto\Admin\Query;

class MerchantChannelListQuery extends PaginationQuery
{
    public ?string $merchantId = null;

    public ?string $salesChannelId = null;

    public ?string $status = null;

    public function toFilters(): array
    {
        $filters = [];

        if ($this->merchantId !== null && $this->merchantId !== '') {
            $filters['merchantId'] = $this->merchantId;
        }

        if ($this->salesChannelId !== null && $this->salesChannelId !== '') {
            $filters['salesChannelId'] = $this->salesChannelId;
        }

        if ($this->status !== null && $this->status !== '') {
            $filters['status'] = $this->status;
        }

        return $filters;
    }
}
