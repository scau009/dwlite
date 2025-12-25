<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateSalesChannelStatusRequest
{
    #[Assert\NotBlank(message: 'validation.status_required')]
    #[Assert\Choice(choices: ['active', 'maintenance', 'disabled'], message: 'validation.status_invalid')]
    public string $status = '';
}