<?php

namespace App\EventSubscriber;

use App\Entity\Merchant;
use App\Entity\User;
use App\Repository\MerchantRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 检查商户是否已通过审核，未通过审核的商户只能访问有限的API.
 */
class MerchantApprovalSubscriber implements EventSubscriberInterface
{
    /**
     * 未审核商户可以访问的API路由白名单.
     */
    private const ALLOWED_ROUTES = [
        '/api/auth/me',
        '/api/auth/logout',
        '/api/auth/refresh',
        '/api/auth/change-password',
        '/api/merchant/profile',  // 允许查看和编辑自己的资料
    ];

    /**
     * 未审核商户可以访问的路由前缀白名单.
     */
    private const ALLOWED_ROUTE_PREFIXES = [
        '/api/auth/',
        '/api/common/',  // 公共接口（如上传文件等）
    ];

    public function __construct(
        private Security $security,
        private MerchantRepository $merchantRepository,
        private TranslatorInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // 优先级设为 -10，确保在认证之后执行
            KernelEvents::REQUEST => ['onKernelRequest', -10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // 只检查 API 请求
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        // 获取当前用户
        $user = $this->security->getUser();

        // 未登录用户不在此处理
        if (!$user instanceof User) {
            return;
        }

        // 只检查商户类型用户
        if (!$user->isMerchant()) {
            return;
        }

        // 检查是否在白名单中
        if ($this->isRouteAllowed($path)) {
            return;
        }

        // 获取商户信息
        $merchant = $this->merchantRepository->findByUser($user);

        // 商户实体不存在（邮箱未验证）
        if ($merchant === null) {
            $event->setResponse($this->createErrorResponse(
                'merchant.approval.not_verified',
                'email_not_verified',
                Response::HTTP_FORBIDDEN
            ));

            return;
        }

        // 检查商户状态
        if ($merchant->isApproved()) {
            return; // 已审核通过，允许访问
        }

        // 根据不同状态返回不同的错误信息
        $errorKey = match ($merchant->getStatus()) {
            Merchant::STATUS_PENDING => 'merchant.approval.pending',
            Merchant::STATUS_REJECTED => 'merchant.approval.rejected',
            Merchant::STATUS_DISABLED => 'merchant.approval.disabled',
            default => 'merchant.approval.not_approved',
        };

        $response = [
            'error' => $this->translator->trans($errorKey),
            'code' => 'merchant_not_approved',
            'merchantStatus' => $merchant->getStatus(),
        ];

        if ($merchant->isRejected() && $merchant->getRejectedReason()) {
            $response['rejectedReason'] = $merchant->getRejectedReason();
        }

        $event->setResponse(new JsonResponse($response, Response::HTTP_FORBIDDEN));
    }

    private function isRouteAllowed(string $path): bool
    {
        // 检查精确匹配
        if (in_array($path, self::ALLOWED_ROUTES, true)) {
            return true;
        }

        // 检查前缀匹配
        foreach (self::ALLOWED_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function createErrorResponse(string $messageKey, string $code, int $status): JsonResponse
    {
        return new JsonResponse([
            'error' => $this->translator->trans($messageKey),
            'code' => $code,
        ], $status);
    }
}
