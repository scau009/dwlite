<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class BatchDeleteSkuRequest
{
    /**
     * @var string[]
     */
    #[Assert\NotBlank(message: 'validation.at_least_one_sku')]
    #[Assert\Count(min: 1, minMessage: 'validation.at_least_one_sku')]
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Type('string'),
    ])]
    public array $skuIds = [];
}
