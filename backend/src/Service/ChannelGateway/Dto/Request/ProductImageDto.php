<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Dto\Request;

/**
 * Product image DTO.
 */
readonly class ProductImageDto
{
    public function __construct(
        public string $url,
        public bool $isPrimary = false,
        public int $sortOrder = 0,
    ) {
    }
}
