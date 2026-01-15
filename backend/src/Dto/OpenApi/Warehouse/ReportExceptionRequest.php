<?php

namespace App\Dto\OpenApi\Warehouse;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request to report an inbound exception.
 */
class ReportExceptionRequest
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['damaged', 'missing', 'wrong_item', 'quantity_mismatch', 'quality_issue', 'other'])]
    public string $type;

    #[Assert\Length(max: 100)]
    public ?string $sku = null;

    #[Assert\Type('integer')]
    #[Assert\Range(min: 0)]
    public ?int $affectedQuantity = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 1000)]
    public string $description;

    /**
     * @var string[]
     */
    #[Assert\All([
        new Assert\Url(),
    ])]
    public array $photoUrls = [];

    #[Assert\NotBlank]
    #[Assert\DateTime]
    public string $reportedAt;
}
