<?php

namespace App\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Carries the serialized message content for monitoring purposes.
 */
final readonly class MessageContentStamp implements StampInterface
{
    public function __construct(
        private string $content,
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
