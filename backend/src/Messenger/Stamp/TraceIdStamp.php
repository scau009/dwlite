<?php

namespace App\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Stamp that carries trace context through async message processing.
 */
final class TraceIdStamp implements StampInterface
{
    public function __construct(
        public readonly string $traceId,
        public readonly ?string $parentSpanId = null,
    ) {
    }
}
