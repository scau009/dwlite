<?php

namespace App\Dto\Admin\Query;

class MerchantListQuery extends PaginationQuery
{
    public ?string $name = null;

    public ?string $status = null;

    public function toFilters(): array
    {
        $filters = [];

        if ($this->name !== null && $this->name !== '') {
            $filters['name'] = $this->name;
        }

        if ($this->status !== null && $this->status !== '') {
            $filters['status'] = $this->status;
        }

        return $filters;
    }
}