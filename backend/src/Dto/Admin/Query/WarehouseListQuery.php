<?php

namespace App\Dto\Admin\Query;

class WarehouseListQuery extends PaginationQuery
{
    public ?string $name = null;

    public ?string $code = null;

    public ?string $type = null;

    public ?string $category = null;

    public ?string $status = null;

    public ?string $countryCode = null;

    public function toFilters(): array
    {
        $filters = [];

        if ($this->name !== null && $this->name !== '') {
            $filters['name'] = $this->name;
        }

        if ($this->code !== null && $this->code !== '') {
            $filters['code'] = $this->code;
        }

        if ($this->type !== null && $this->type !== '') {
            $filters['type'] = $this->type;
        }

        if ($this->category !== null && $this->category !== '') {
            $filters['category'] = $this->category;
        }

        if ($this->status !== null && $this->status !== '') {
            $filters['status'] = $this->status;
        }

        if ($this->countryCode !== null && $this->countryCode !== '') {
            $filters['countryCode'] = $this->countryCode;
        }

        return $filters;
    }
}
