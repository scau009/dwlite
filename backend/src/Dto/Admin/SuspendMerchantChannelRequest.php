<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class SuspendMerchantChannelRequest
{
    #[Assert\Length(max: 255, maxMessage: 'validation.remark_max_length')]
    public ?string $reason = null;
}
