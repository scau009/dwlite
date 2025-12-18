<?php

namespace App\EventSubscriber;

use App\Attribute\AdminOnly;
use App\Entity\User;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * 检查 #[AdminOnly] 属性并拒绝非管理员访问
 */
class AdminAccessSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 0],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();

        // 处理数组形式的控制器 [ControllerClass, 'methodName']
        if (is_array($controller)) {
            [$controllerObject, $methodName] = $controller;
        } else {
            // 处理可调用对象（如闭包）
            return;
        }

        // 检查类级别或方法级别是否有 #[AdminOnly] 属性
        if (!$this->hasAdminOnlyAttribute($controllerObject, $methodName)) {
            return;
        }

        // 获取当前用户
        $user = $this->security->getUser();

        if (!$user instanceof User || !$user->isAdmin()) {
            $event->setController(function () {
                return new JsonResponse(
                    ['error' => 'Access denied'],
                    Response::HTTP_FORBIDDEN
                );
            });
        }
    }

    private function hasAdminOnlyAttribute(object $controller, string $methodName): bool
    {
        $reflectionClass = new ReflectionClass($controller);

        // 检查类级别的属性
        if ($reflectionClass->getAttributes(AdminOnly::class)) {
            return true;
        }

        // 检查方法级别的属性
        if ($reflectionClass->hasMethod($methodName)) {
            $reflectionMethod = new ReflectionMethod($controller, $methodName);
            if ($reflectionMethod->getAttributes(AdminOnly::class)) {
                return true;
            }
        }

        return false;
    }
}
