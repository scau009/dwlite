<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateStatusRequest
{
    #[Assert\NotNull(message: 'isActive field is required')]
    public bool $isActive = false;
}
