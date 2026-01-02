<?php

namespace App\Dto\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

class ApplyChannelRequest
{
    #[Assert\NotBlank(message: 'validation.sales_channel_id_required')]
    #[Assert\Length(exactly: 26, exactMessage: 'validation.sales_channel_id_invalid')]
    public string $salesChannelId;

    #[Assert\NotBlank(message: 'validation.fulfillment_type_required')]
    #[Assert\Choice(choices: ['consignment', 'self_fulfillment'], message: 'validation.fulfillment_type_invalid')]
    public string $fulfillmentType;

    #[Assert\Choice(choices: ['self_pricing', 'platform_managed'], message: 'validation.pricing_model_invalid')]
    public string $pricingModel = 'self_pricing';

    #[Assert\Length(max: 255, maxMessage: 'validation.remark_too_long')]
    public ?string $remark = null;
}
