<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * 批量更新商品状态请求.
 */
class BatchUpdateProductStatusRequest
{
    /**
     * @var string[]
     */
    #[Assert\NotBlank(message: 'validation.at_least_one_product')]
    #[Assert\Count(min: 1, minMessage: 'validation.at_least_one_product')]
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Type('string'),
    ])]
    public array $productIds = [];

    #[Assert\NotBlank(message: 'validation.status_required')]
    #[Assert\Choice(choices: ['draft', 'active', 'inactive'], message: 'validation.invalid_status')]
    public string $status = '';
}
