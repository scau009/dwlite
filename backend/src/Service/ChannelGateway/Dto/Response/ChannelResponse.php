<?php

declare(strict_types=1);

namespace App\Service\ChannelGateway\Dto\Response;

/**
 * Standard response wrapper for channel operations.
 */
readonly class ChannelResponse
{
    /**
     * @param array<string, mixed>|null $data Additional response data
     */
    public function __construct(
        public bool $success,
        public string $channelCode,
        public ?string $externalId = null,
        public ?string $message = null,
        public ?array $data = null,
        public ?\DateTimeImmutable $timestamp = null,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }
}
