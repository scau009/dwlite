<?php

declare(strict_types=1);

namespace App\Dto\Admin\Query;

class PayoutListQuery extends PaginationQuery
{
    public ?string $payoutNo = null;

    public ?string $merchantId = null;

    public ?string $status = null;

    public ?string $createdAtFrom = null;

    public ?string $createdAtTo = null;

    public function toFilters(): array
    {
        $filters = [];

        if ($this->payoutNo !== null && $this->payoutNo !== '') {
            $filters['payoutNo'] = $this->payoutNo;
        }

        if ($this->merchantId !== null && $this->merchantId !== '') {
            $filters['merchantId'] = $this->merchantId;
        }

        if ($this->status !== null && $this->status !== '') {
            $filters['status'] = $this->status;
        }

        if ($this->createdAtFrom !== null && $this->createdAtFrom !== '') {
            $filters['createdAtFrom'] = new \DateTimeImmutable($this->createdAtFrom, new \DateTimeZone('UTC'));
        }

        if ($this->createdAtTo !== null && $this->createdAtTo !== '') {
            $filters['createdAtTo'] = new \DateTimeImmutable($this->createdAtTo, new \DateTimeZone('UTC'));
        }

        return $filters;
    }
}
