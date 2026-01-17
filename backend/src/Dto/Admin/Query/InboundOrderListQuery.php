<?php

declare(strict_types=1);

namespace App\Dto\Admin\Query;

class InboundOrderListQuery extends PaginationQuery
{
    public ?string $merchantId = null;

    public ?string $warehouseId = null;

    public ?string $status = null;

    public ?string $search = null;

    public ?string $trackingNumber = null;

    public ?string $startDate = null;

    public ?string $endDate = null;

    public function toFilters(): array
    {
        $filters = [];

        if ($this->merchantId !== null && $this->merchantId !== '') {
            $filters['merchantId'] = $this->merchantId;
        }

        if ($this->warehouseId !== null && $this->warehouseId !== '') {
            $filters['warehouseId'] = $this->warehouseId;
        }

        if ($this->status !== null && $this->status !== '') {
            $filters['status'] = $this->status;
        }

        if ($this->search !== null && $this->search !== '') {
            $filters['search'] = trim($this->search);
        }

        if ($this->trackingNumber !== null && $this->trackingNumber !== '') {
            $filters['trackingNumber'] = trim($this->trackingNumber);
        }

        if ($this->startDate !== null && $this->startDate !== '') {
            $filters['startDate'] = $this->startDate;
        }

        if ($this->endDate !== null && $this->endDate !== '') {
            $filters['endDate'] = $this->endDate;
        }

        return $filters;
    }
}
