<?php

namespace App\Dto\Admin\Query;

class TagListQuery extends PaginationQuery
{
    public ?string $name = null;

    public ?bool $isActive = null;

    public function toFilters(): array
    {
        $filters = [];

        if ($this->name !== null && $this->name !== '') {
            $filters['name'] = $this->name;
        }

        if ($this->isActive !== null) {
            $filters['isActive'] = $this->isActive;
        }

        return $filters;
    }
}