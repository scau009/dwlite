<?php

declare(strict_types=1);

namespace App\Dto\Admin\Query;

class OrderListQuery extends PaginationQuery
{
    public ?string $salesChannelId = null;

    public ?string $status = null;

    public ?string $paymentStatus = null;

    public ?string $search = null;

    public ?string $startDate = null;

    public ?string $endDate = null;

    public function toFilters(): array
    {
        $filters = [];

        if ($this->salesChannelId !== null && $this->salesChannelId !== '') {
            $filters['salesChannelId'] = $this->salesChannelId;
        }

        if ($this->status !== null && $this->status !== '') {
            $filters['status'] = $this->status;
        }

        if ($this->paymentStatus !== null && $this->paymentStatus !== '') {
            $filters['paymentStatus'] = $this->paymentStatus;
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
