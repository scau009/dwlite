<?php

declare(strict_types=1);

namespace App\Dto\Merchant\Query;

use App\Dto\Admin\Query\PaginationQuery;

class FulfillmentListQuery extends PaginationQuery
{
    public ?string $status = null;

    public ?string $fulfillmentType = null;

    public function getStatus(): ?string
    {
        return $this->status !== null && $this->status !== '' ? $this->status : null;
    }

    public function getFulfillmentType(): ?string
    {
        return $this->fulfillmentType !== null && $this->fulfillmentType !== '' ? $this->fulfillmentType : null;
    }
}
