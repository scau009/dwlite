<?php

declare(strict_types=1);

namespace App\Dto\Merchant\Query;

use App\Dto\Admin\Query\PaginationQuery;

class MerchantPayoutListQuery extends PaginationQuery
{
    public ?string $payoutNo = null;

    public ?string $status = null;

    public ?string $createdAtFrom = null;

    public ?string $createdAtTo = null;

    public function toFilters(): array
    {
        $filters = [];

        if ($this->payoutNo !== null && $this->payoutNo !== '') {
            $filters['payoutNo'] = $this->payoutNo;
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
