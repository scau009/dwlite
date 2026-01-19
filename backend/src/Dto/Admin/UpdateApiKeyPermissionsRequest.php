<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateApiKeyPermissionsRequest
{
    /**
     * @var array<string>
     */
    #[Assert\NotBlank(message: 'validation.permissions_required')]
    #[Assert\Type(type: 'array', message: 'validation.permissions_must_be_array')]
    public array $permissions = [];
}
