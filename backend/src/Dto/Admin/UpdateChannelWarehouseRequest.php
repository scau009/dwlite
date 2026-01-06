<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateChannelWarehouseRequest
{
    #[Assert\PositiveOrZero(message: 'validation.priority_positive')]
    public ?int $priority = null;

    #[Assert\Choice(choices: ['active', 'disabled'], message: 'validation.status_invalid')]
    public ?string $status = null;

    #[Assert\Length(max: 255, maxMessage: 'validation.remark_max_length')]
    public ?string $remark = null;
}
