<?php

namespace App\Dto\Merchant;

use Symfony\Component\Validator\Constraints as Assert;

class ImportInventoryPreviewRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 26)]
    public string $warehouseId;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['skip', 'override', 'add'])]
    public string $conflictStrategy = 'skip';
}
