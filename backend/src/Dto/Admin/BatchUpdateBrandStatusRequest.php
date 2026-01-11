<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class BatchUpdateBrandStatusRequest
{
    /**
     * @var string[]
     */
    #[Assert\NotBlank(message: 'validation.at_least_one_brand')]
    #[Assert\Count(min: 1, minMessage: 'validation.at_least_one_brand')]
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Type('string'),
    ])]
    public array $ids = [];

    #[Assert\NotNull(message: 'validation.status_required')]
    #[Assert\Type('bool')]
    public bool $isActive;
}
