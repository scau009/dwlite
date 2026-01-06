<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class BatchAddChannelWarehousesRequest
{
    /**
     * @var string[]
     */
    #[Assert\NotBlank(message: 'validation.warehouse_ids_required')]
    #[Assert\Type('array', message: 'validation.warehouse_ids_must_be_array')]
    #[Assert\Count(min: 1, minMessage: 'validation.warehouse_ids_min_count')]
    #[Assert\All([
        new Assert\Ulid(message: 'validation.warehouse_id_invalid'),
    ])]
    public array $warehouseIds = [];
}
