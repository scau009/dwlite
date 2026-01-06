<?php

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class UpdatePrioritiesRequest
{
    /**
     * @var array<int, array{id: string, priority: int}>
     */
    #[Assert\NotBlank(message: 'validation.items_required')]
    #[Assert\Type('array', message: 'validation.items_must_be_array')]
    #[Assert\Count(min: 1, minMessage: 'validation.items_min_count')]
    #[Assert\All([
        new Assert\Collection([
            'id' => [
                new Assert\NotBlank(message: 'validation.id_required'),
                new Assert\Ulid(message: 'validation.id_invalid'),
            ],
            'priority' => [
                new Assert\NotBlank(message: 'validation.priority_required'),
                new Assert\PositiveOrZero(message: 'validation.priority_positive'),
            ],
        ]),
    ])]
    public array $items = [];
}
