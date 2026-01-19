<?php

namespace App\Dto\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

class ResubmitChannelRequest
{
    /**
     * @var string[] 申请的履约模式
     */
    #[Assert\NotBlank(message: 'validation.fulfillment_types_required')]
    #[Assert\Count(min: 1, minMessage: 'validation.fulfillment_types_required')]
    #[Assert\All([
        new Assert\Choice(choices: ['consignment', 'self_fulfillment'], message: 'validation.fulfillment_type_invalid'),
    ])]
    public array $fulfillmentTypes = ['consignment'];

    #[Assert\Length(max: 255, maxMessage: 'validation.remark_too_long')]
    public ?string $remark = null;
}
