<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateMerchantStatusRequest
{
    #[Assert\NotNull(message: 'enabled field is required')]
    public bool $enabled = false;
}
