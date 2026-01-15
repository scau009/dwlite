<?php

namespace App\EventSubscriber;

use App\Entity\ApiKeyLog;
use App\Security\ApiKeyUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Subscriber to log Open API requests.
 */
class OpenApiLogSubscriber implements EventSubscriberInterface
{
    private ?float $startTime = null;

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Only log Open API requests
        if (!str_starts_with($request->getPathInfo(), '/api/v1/open/')) {
            return;
        }

        $this->startTime = hrtime(true);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || $this->startTime === null) {
            return;
        }

        $request = $event->getRequest();

        // Only log Open API requests
        if (!str_starts_with($request->getPathInfo(), '/api/v1/open/')) {
            return;
        }

        // Get the current user
        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof ApiKeyUser) {
            return;
        }

        $apiKey = $user->getApiKey();
        $response = $event->getResponse();

        // Calculate response time
        $endTime = hrtime(true);
        $responseTime = (int) (($endTime - $this->startTime) / 1_000_000); // Convert to milliseconds

        // Get error code from response if available
        $errorCode = null;
        if ($response->getStatusCode() >= 400) {
            try {
                $content = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
                $errorCode = $content['error']['code'] ?? null;
            } catch (\JsonException) {
                // Ignore JSON errors
            }
        }

        // Create log entry
        $log = ApiKeyLog::create(
            $apiKey,
            $request->getPathInfo(),
            $request->getMethod(),
            $request->attributes->get('request_id') ?? '',
            $request->getClientIp() ?? '',
            $response->getStatusCode(),
            $responseTime,
            $request->headers->get('User-Agent'),
            strlen($request->getContent()),
            $errorCode
        );

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        // Reset start time
        $this->startTime = null;
    }
}
