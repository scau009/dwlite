<?php

namespace App\EventSubscriber;

use App\Attribute\WarehouseOnly;
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
 * 检查 #[WarehouseOnly] 属性并拒绝非仓库操作员访问
 */
class WarehouseAccessSubscriber implements EventSubscriberInterface
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

        // 检查类级别或方法级别是否有 #[WarehouseOnly] 属性
        if (!$this->hasWarehouseOnlyAttribute($controllerObject, $methodName)) {
            return;
        }

        // 获取当前用户
        $user = $this->security->getUser();

        // 仓库操作员必须有关联的仓库
        if (!$user instanceof User) {
            $event->setController(function () {
                return new JsonResponse(
                    ['error' => 'User not authenticated'],
                    Response::HTTP_UNAUTHORIZED
                );
            });
            return;
        }

        if (!$user->isWarehouse()) {
            $event->setController(function () {
                return new JsonResponse(
                    ['error' => 'Warehouse access only'],
                    Response::HTTP_FORBIDDEN
                );
            });
            return;
        }

        if ($user->getWarehouse() === null) {
            $event->setController(function () {
                return new JsonResponse(
                    ['error' => 'No warehouse assigned'],
                    Response::HTTP_FORBIDDEN
                );
            });
            return;
        }

        // 将仓库设置到请求属性中，供控制器使用
        $event->getRequest()->attributes->set('warehouse', $user->getWarehouse());
    }

    private function hasWarehouseOnlyAttribute(object $controller, string $methodName): bool
    {
        $reflectionClass = new ReflectionClass($controller);

        // 检查类级别的属性
        if ($reflectionClass->getAttributes(WarehouseOnly::class)) {
            return true;
        }

        // 检查方法级别的属性
        if ($reflectionClass->hasMethod($methodName)) {
            $reflectionMethod = new ReflectionMethod($controller, $methodName);
            if ($reflectionMethod->getAttributes(WarehouseOnly::class)) {
                return true;
            }
        }

        return false;
    }
}
