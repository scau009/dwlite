<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateMerchantStatusRequest
{
    #[Assert\NotNull(message: 'validation.enabled_required')]
    public bool $enabled = false;
}
