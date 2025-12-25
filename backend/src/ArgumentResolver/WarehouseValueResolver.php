<?php

namespace App\ArgumentResolver;

use App\Entity\User;
use App\Entity\Warehouse;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * 自动为 #[WarehouseOnly] 标记的控制器方法注入当前用户的 Warehouse 实体
 *
 * 使用方法：在控制器方法参数中声明 Warehouse $warehouse 即可自动注入
 */
class WarehouseValueResolver implements ValueResolverInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        // 只处理 Warehouse 类型的参数
        if ($argument->getType() !== Warehouse::class) {
            return [];
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('User not authenticated');
        }

        $warehouse = $user->getWarehouse();

        if ($warehouse === null) {
            throw new AccessDeniedHttpException('No warehouse assigned');
        }

        yield $warehouse;
    }
}
