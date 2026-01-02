<?php

namespace App\Dto\Admin\Query;

class CategoryListQuery extends PaginationQuery
{
    public ?string $name = null;

    public ?bool $isActive = null;

    public ?string $parentId = null;

    public function toFilters(): array
    {
        $filters = [];

        if ($this->name !== null && $this->name !== '') {
            $filters['name'] = $this->name;
        }

        if ($this->isActive !== null) {
            $filters['isActive'] = $this->isActive;
        }

        if ($this->parentId !== null) {
            $filters['parentId'] = $this->parentId === '' || $this->parentId === 'null' ? null : $this->parentId;
        }

        return $filters;
    }
}
