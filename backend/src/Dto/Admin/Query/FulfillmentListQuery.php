<?php

declare(strict_types=1);

namespace App\Dto\Admin\Query;

class FulfillmentListQuery extends PaginationQuery
{
    public ?string $status = null;

    public ?string $fulfillmentType = null;

    public ?string $merchantId = null;

    public ?string $warehouseId = null;

    public ?string $orderId = null;

    public ?string $search = null;

    public ?string $startDate = null;

    public ?string $endDate = null;

    public function toFilters(): array
    {
        $filters = [];

        if ($this->status !== null && $this->status !== '') {
            $filters['status'] = $this->status;
        }

        if ($this->fulfillmentType !== null && $this->fulfillmentType !== '') {
            $filters['fulfillmentType'] = $this->fulfillmentType;
        }

        if ($this->merchantId !== null && $this->merchantId !== '') {
            $filters['merchantId'] = $this->merchantId;
        }

        if ($this->warehouseId !== null && $this->warehouseId !== '') {
            $filters['warehouseId'] = $this->warehouseId;
        }

        if ($this->orderId !== null && $this->orderId !== '') {
            $filters['orderId'] = $this->orderId;
        }

        if ($this->search !== null && $this->search !== '') {
            $filters['search'] = trim($this->search);
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
