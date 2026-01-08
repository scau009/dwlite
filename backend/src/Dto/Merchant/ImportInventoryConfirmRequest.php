<?php

namespace App\Dto\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

class ImportInventoryConfirmRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 26)]
    public string $warehouseId;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['skip', 'override', 'add'])]
    public string $conflictStrategy = 'skip';

    /**
     * @var ImportInventoryConfirmItemRequest[]
     */
    #[Assert\NotBlank]
    #[Assert\Valid]
    #[Assert\All([
        new Assert\Type(ImportInventoryConfirmItemRequest::class),
    ])]
    public array $items = [];
}
