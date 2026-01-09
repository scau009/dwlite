<?php

declare(strict_types=1);

namespace App\Dto\Merchant\Query;

use App\Dto\Admin\Query\PaginationQuery;

class MerchantSettlementListQuery extends PaginationQuery
{
    public ?string $settlementNo = null;

    public ?string $status = null;

    public ?string $scheduledSettleAtFrom = null;

    public ?string $scheduledSettleAtTo = null;

    public function toFilters(): array
    {
        $filters = [];

        if ($this->settlementNo !== null && $this->settlementNo !== '') {
            $filters['settlementNo'] = $this->settlementNo;
        }

        if ($this->status !== null && $this->status !== '') {
            $filters['status'] = $this->status;
        }

        if ($this->scheduledSettleAtFrom !== null && $this->scheduledSettleAtFrom !== '') {
            $filters['scheduledSettleAtFrom'] = new \DateTimeImmutable($this->scheduledSettleAtFrom, new \DateTimeZone('UTC'));
        }

        if ($this->scheduledSettleAtTo !== null && $this->scheduledSettleAtTo !== '') {
            $filters['scheduledSettleAtTo'] = new \DateTimeImmutable($this->scheduledSettleAtTo, new \DateTimeZone('UTC'));
        }

        return $filters;
    }
}
