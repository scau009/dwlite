<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class CreateWarehouseUserRequest
{
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Invalid email format')]
    public string $email;

    #[Assert\NotBlank(message: 'Password is required')]
    #[Assert\Length(min: 8, minMessage: 'Password must be at least 8 characters')]
    public string $password;

    #[Assert\NotBlank(message: 'Warehouse ID is required')]
    public string $warehouseId;
}
