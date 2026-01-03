<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class ApproveMerchantChannelRequest
{
    /**
     * @var string[]|null 批准的履约模式，null 表示批准所有申请的模式
     */
    #[Assert\All([
        new Assert\Choice(choices: ['consignment', 'self_fulfillment'], message: 'validation.fulfillment_type_invalid'),
    ])]
    public ?array $approvedFulfillmentTypes = null;
}