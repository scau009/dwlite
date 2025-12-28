<?php

namespace App\Dto\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

class ApplyChannelRequest
{
    #[Assert\NotBlank(message: 'validation.sales_channel_id_required')]
    #[Assert\Length(exactly: 26, exactMessage: 'validation.sales_channel_id_invalid')]
    public string $salesChannelId;

    #[Assert\Length(max: 255, maxMessage: 'validation.remark_too_long')]
    public ?string $remark = null;
}
