<?php

namespace App\Dto\Inbound;

use Symfony\Component\Validator\Constraints as Assert;

class ResolveInboundExceptionRequest
{
    #[Assert\NotBlank(message: 'validation.resolution_required')]
    #[Assert\Choice(
        choices: ['accept', 'reject', 'claim', 'recount', 'partial_accept'],
        message: 'validation.invalid_resolution'
    )]
    public string $resolution = '';

    #[Assert\Type('string')]
    public ?string $resolutionNotes = null;

    #[Assert\Type('numeric')]
    #[Assert\PositiveOrZero(message: 'validation.claim_amount_must_non_negative')]
    public ?string $claimAmount = null;

    #[Assert\Type('string')]
    public ?string $resolvedBy = null;
}