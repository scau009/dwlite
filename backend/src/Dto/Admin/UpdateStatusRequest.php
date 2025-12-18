<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateStatusRequest
{
    #[Assert\NotNull(message: 'validation.is_active_required')]
    public bool $isActive = false;
}
