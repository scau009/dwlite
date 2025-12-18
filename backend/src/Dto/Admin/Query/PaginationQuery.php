<?php

namespace App\Dto\Admin\Query;

use Symfony\Component\Validator\Constraints as Assert;

class PaginationQuery
{
    #[Assert\Positive]
    public int $page = 1;

    #[Assert\Range(min: 1, max: 100)]
    public int $limit = 20;

    public function getPage(): int
    {
        return max(1, $this->page);
    }

    public function getLimit(): int
    {
        return min(100, max(1, $this->limit));
    }

    public function toFilters(): array
    {
        return [];
    }
}