<?php

namespace App\EventSubscriber;

use App\Service\MetricsService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class MetricsSubscriber implements EventSubscriberInterface
{
    private float $startTime;

    public function __construct(
        private MetricsService $metricsService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 1000],
            KernelEvents::TERMINATE => ['onTerminate', -1000],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->startTime = microtime(true);
    }

    public function onTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        // Skip metrics endpoint to avoid self-reporting
        if ($request->getPathInfo() === '/metrics') {
            return;
        }

        $duration = microtime(true) - $this->startTime;
        $method = $request->getMethod();
        $route = $request->attributes->get('_route', 'unknown');
        $statusCode = $response->getStatusCode();

        $this->metricsService->recordRequestDuration($method, $route, $statusCode, $duration);
        $this->metricsService->incrementRequestCount($method, $route, $statusCode);
    }
}
