<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Dto\Response;

/**
 * Response DTO for stock/price update operation.
 */
readonly class UpdateStockPriceResponse extends ChannelResponse
{
    /**
     * @param array<string, bool> $results Map of externalId => success
     */
    public function __construct(
        bool $success,
        string $channelCode,
        public int $updatedCount = 0,
        public array $results = [],
        ?string $message = null,
        ?array $data = null,
        ?\DateTimeImmutable $timestamp = null,
    ) {
        parent::__construct($success, $channelCode, null, $message, $data, $timestamp);
    }
}
