<?php

namespace App\Dto\Admin\Query;

class SalesChannelListQuery extends PaginationQuery
{
    public ?string $name = null;

    public ?string $code = null;

    public ?string $businessType = null;

    public ?string $status = null;

    public function toFilters(): array
    {
        $filters = [];

        if ($this->name !== null && $this->name !== '') {
            $filters['name'] = $this->name;
        }

        if ($this->code !== null && $this->code !== '') {
            $filters['code'] = $this->code;
        }

        if ($this->businessType !== null && $this->businessType !== '') {
            $filters['businessType'] = $this->businessType;
        }

        if ($this->status !== null && $this->status !== '') {
            $filters['status'] = $this->status;
        }

        return $filters;
    }
}
