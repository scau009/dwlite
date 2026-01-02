<?php

namespace App\Dto\Admin\Query;

class MerchantListQuery extends PaginationQuery
{
    public ?string $name = null;

    public ?string $status = null;

    public ?string $email = null;

    public function toFilters(): array
    {
        $filters = [];

        if ($this->name !== null && $this->name !== '') {
            $filters['name'] = $this->name;
        }

        if ($this->status !== null && $this->status !== '') {
            $filters['status'] = $this->status;
        }

        if ($this->email !== null && $this->email !== '') {
            $filters['email'] = $this->email;
        }

        return $filters;
    }
}
