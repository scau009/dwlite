<?php

namespace App\EventSubscriber;

use App\Attribute\OpenApiOnly;
use App\Security\ApiKeyUser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Subscriber to enforce OpenApiOnly attribute and check permissions.
 */
class OpenApiAccessSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 10],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();

        if (!is_array($controller)) {
            return;
        }

        [$controllerObject, $methodName] = $controller;
        $reflection = new \ReflectionClass($controllerObject);
        $method = $reflection->getMethod($methodName);

        // Check for OpenApiOnly attribute on class or method
        $classAttributes = $reflection->getAttributes(OpenApiOnly::class);
        $methodAttributes = $method->getAttributes(OpenApiOnly::class);

        if (empty($classAttributes) && empty($methodAttributes)) {
            return;
        }

        // Get the OpenApiOnly attribute (method takes precedence)
        $attribute = null;
        if (!empty($methodAttributes)) {
            $attribute = $methodAttributes[0]->newInstance();
        } elseif (!empty($classAttributes)) {
            $attribute = $classAttributes[0]->newInstance();
        }

        if ($attribute === null) {
            return;
        }

        // Get the current user
        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            throw new AccessDeniedHttpException('Authentication required');
        }

        $user = $token->getUser();
        if (!$user instanceof ApiKeyUser) {
            throw new AccessDeniedHttpException('API Key authentication required');
        }

        // Check permission if specified
        if ($attribute->permission !== null) {
            if (!$user->hasPermission($attribute->permission)) {
                throw new AccessDeniedHttpException(
                    sprintf('Missing required permission: %s', $attribute->permission)
                );
            }
        }

        // Inject API Key into request attributes for easy access in controllers
        $request = $event->getRequest();
        $request->attributes->set('api_key', $user->getApiKey());

        // Inject merchant or warehouse based on API Key type
        $apiKey = $user->getApiKey();
        if ($apiKey->isMerchantType() && $apiKey->getMerchant() !== null) {
            $request->attributes->set('merchant', $apiKey->getMerchant());
        } elseif ($apiKey->isWarehouseType() && $apiKey->getWarehouse() !== null) {
            $request->attributes->set('warehouse', $apiKey->getWarehouse());
        }
    }
}
