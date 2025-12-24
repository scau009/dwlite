<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateWarehouseUserRequest
{
    #[Assert\Email(message: 'Invalid email format')]
    public ?string $email = null;

    #[Assert\Length(min: 8, minMessage: 'Password must be at least 8 characters')]
    public ?string $password = null;

    public ?string $warehouseId = null;

    public ?bool $isActive = null;
}
