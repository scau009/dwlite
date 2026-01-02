<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class AddChannelWarehouseRequest
{
    #[Assert\NotBlank(message: 'validation.warehouse_id_required')]
    #[Assert\Ulid(message: 'validation.warehouse_id_invalid')]
    public string $warehouseId = '';

    #[Assert\PositiveOrZero(message: 'validation.priority_positive')]
    public ?int $priority = null;

    #[Assert\Length(max: 255, maxMessage: 'validation.remark_max_length')]
    public ?string $remark = null;
}
