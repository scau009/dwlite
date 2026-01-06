<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Dto\Response;

/**
 * Response DTO for product push operation.
 */
readonly class PushProductResponse extends ChannelResponse
{
    public function __construct(
        bool $success,
        string $channelCode,
        ?string $externalId = null,
        public ?string $externalUrl = null,
        ?string $message = null,
        ?array $data = null,
        ?\DateTimeImmutable $timestamp = null,
    ) {
        parent::__construct($success, $channelCode, $externalId, $message, $data, $timestamp);
    }
}
