<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateApiKeyIpWhitelistRequest
{
    /**
     * @var array<string>|null
     */
    #[Assert\Type(type: 'array', message: 'validation.ip_whitelist_must_be_array')]
    public ?array $ipWhitelist = null;
}
