<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateProductStatusRequest
{
    #[Assert\NotBlank(message: 'validation.status_required')]
    #[Assert\Choice(choices: ['draft', 'active', 'inactive', 'discontinued'], message: 'validation.invalid_status')]
    public string $status = '';
}
