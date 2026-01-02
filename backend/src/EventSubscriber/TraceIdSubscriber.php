<?php

namespace App\EventSubscriber;

use App\Service\RequestIdService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class TraceIdSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RequestIdService $requestIdService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 255],
            KernelEvents::RESPONSE => ['onResponse', -255],
            KernelEvents::TERMINATE => ['onTerminate', -255],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Support W3C Trace Context and custom headers
        $traceId = $request->headers->get('X-Trace-Id')
            ?? $this->extractTraceIdFromTraceparent($request->headers->get('traceparent'));
        $parentSpanId = $request->headers->get('X-Parent-Span-Id');

        $this->requestIdService->setFromHeaders($traceId, $parentSpanId);
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set('X-Trace-Id', $this->requestIdService->getTraceId());
        $response->headers->set('X-Span-Id', $this->requestIdService->getSpanId());
    }

    public function onTerminate(TerminateEvent $event): void
    {
        $this->requestIdService->reset();
    }

    private function extractTraceIdFromTraceparent(?string $traceparent): ?string
    {
        if ($traceparent === null) {
            return null;
        }

        // W3C Trace Context format: 00-{trace-id}-{parent-id}-{flags}
        $parts = explode('-', $traceparent);

        return $parts[1] ?? null;
    }
}
