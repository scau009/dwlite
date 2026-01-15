<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateApiKeyStatusRequest
{
    #[Assert\NotBlank(message: 'validation.status_required')]
    #[Assert\Choice(choices: ['active', 'suspended'], message: 'validation.status_invalid')]
    public string $status = '';
}
