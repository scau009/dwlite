<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Dto\Request;

/**
 * Request DTO for pulling orders from external channel.
 */
readonly class PullOrdersRequest
{
    public function __construct(
        public \DateTimeImmutable $startTime,
        public \DateTimeImmutable $endTime,
        public ?string $status = null,
        public int $page = 1,
        public int $pageSize = 100,
    ) {
    }
}
