<?php

namespace App\Dto\Admin\Query;

class ApiKeyListQuery extends PaginationQuery
{
    public ?string $type = null;
    public ?string $status = null;
    public ?string $merchantId = null;
    public ?string $warehouseId = null;

    /**
     * Build filters array for repository query.
     *
     * @return array{type?: string, status?: string, merchantId?: string, warehouseId?: string}
     */
    public function toFilters(): array
    {
        $filters = [];

        if ($this->type !== null) {
            $filters['type'] = $this->type;
        }

        if ($this->status !== null) {
            $filters['status'] = $this->status;
        }

        if ($this->merchantId !== null) {
            $filters['merchantId'] = $this->merchantId;
        }

        if ($this->warehouseId !== null) {
            $filters['warehouseId'] = $this->warehouseId;
        }

        return $filters;
    }
}
