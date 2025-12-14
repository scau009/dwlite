<?php

namespace App\Messenger\Middleware;

use App\Messenger\Stamp\TraceIdStamp;
use App\Service\RequestIdService;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Middleware that propagates trace context through async messages.
 *
 * - On dispatch: captures current trace_id and span_id, adds TraceIdStamp
 * - On receive: extracts TraceIdStamp and restores context to RequestIdService
 */
final class TraceIdMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RequestIdService $requestIdService,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // Check if this is a received message (being handled by worker)
        if ($envelope->last(ReceivedStamp::class) !== null) {
            $this->restoreTraceContext($envelope);
        } else {
            // Message is being dispatched - add trace context
            $envelope = $this->addTraceContext($envelope);
        }

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            // Reset context after handling in worker
            if ($envelope->last(ReceivedStamp::class) !== null) {
                $this->requestIdService->reset();
            }
        }
    }

    private function addTraceContext(Envelope $envelope): Envelope
    {
        // Don't add if already has stamp
        if ($envelope->last(TraceIdStamp::class) !== null) {
            return $envelope;
        }

        return $envelope->with(new TraceIdStamp(
            traceId: $this->requestIdService->getTraceId(),
            parentSpanId: $this->requestIdService->getSpanId(),
        ));
    }

    private function restoreTraceContext(Envelope $envelope): void
    {
        $stamp = $envelope->last(TraceIdStamp::class);

        if ($stamp instanceof TraceIdStamp) {
            $this->requestIdService->setFromHeaders(
                $stamp->traceId,
                $stamp->parentSpanId,
            );
        }
    }
}
