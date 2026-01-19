<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\OrderException;
use App\Message\PushOrderStatusMessage;
use App\Repository\OrderExceptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * 订单异常工单控制器.
 */
#[Route('/api/order-exceptions')]
#[IsGranted('ROLE_USER')]
class OrderExceptionController extends AbstractController
{
    public function __construct(
        private readonly OrderExceptionRepository $exceptionRepo,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * 获取异常工单列表.
     */
    #[Route('', name: 'order_exception_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', '1'));
        $pageSize = min((int) $request->query->get('pageSize', '20'), 100);
        $status = $request->query->get('status');
        $type = $request->query->get('type');
        $orderId = $request->query->get('orderId');
        $salesChannelId = $request->query->get('salesChannelId');

        $result = $this->exceptionRepo->findPaginated(
            page: $page,
            pageSize: $pageSize,
            status: $status,
            type: $type,
            orderId: $orderId,
            salesChannelId: $salesChannelId,
        );

        return $this->json([
            'data' => array_map(fn (OrderException $e) => $this->formatException($e), $result['items']),
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $result['total'],
                'totalPages' => (int) ceil($result['total'] / $pageSize),
            ],
        ]);
    }

    /**
     * 获取异常工单详情.
     */
    #[Route('/{id}', name: 'order_exception_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $exception = $this->exceptionRepo->find($id);

        if ($exception === null) {
            return $this->json(['error' => 'Exception not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'data' => $this->formatException($exception, true),
        ]);
    }

    /**
     * 处理异常工单.
     */
    #[Route('/{id}/resolve', name: 'order_exception_resolve', methods: ['POST'])]
    public function resolve(string $id, Request $request): JsonResponse
    {
        $exception = $this->exceptionRepo->find($id);

        if ($exception === null) {
            return $this->json(['error' => 'Exception not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$exception->isPending()) {
            return $this->json(['error' => 'Exception already resolved'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $resolution = $data['resolution'] ?? null;
        $notes = $data['notes'] ?? null;

        if ($resolution === null) {
            return $this->json(['error' => 'Resolution is required'], Response::HTTP_BAD_REQUEST);
        }

        $validResolutions = [
            OrderException::RESOLUTION_CONFIRM,
            OrderException::RESOLUTION_CANCEL,
            OrderException::RESOLUTION_ADJUSTED,
        ];

        if (!in_array($resolution, $validResolutions, true)) {
            return $this->json(['error' => 'Invalid resolution'], Response::HTTP_BAD_REQUEST);
        }

        // 获取当前用户 ID
        $user = $this->getUser();
        $resolvedBy = $user?->getUserIdentifier();

        // 解决异常
        $exception->resolve($resolution, $notes, $resolvedBy);
        $this->entityManager->flush();

        // 根据处理方式派发消息
        $order = $exception->getOrder();

        if ($resolution === OrderException::RESOLUTION_CONFIRM) {
            // 检查是否还有其他待处理异常
            $pendingCount = $this->exceptionRepo->countPendingByOrder($order);
            if ($pendingCount === 0) {
                // 所有异常已处理，派发确认消息
                $this->messageBus->dispatch(PushOrderStatusMessage::confirm($order->getId()));
            }
        } elseif ($resolution === OrderException::RESOLUTION_CANCEL) {
            // 取消订单
            $this->messageBus->dispatch(PushOrderStatusMessage::cancel($order->getId()));
        }

        return $this->json([
            'data' => $this->formatException($exception),
            'message' => 'Exception resolved successfully',
        ]);
    }

    /**
     * 关闭异常工单.
     */
    #[Route('/{id}/close', name: 'order_exception_close', methods: ['POST'])]
    public function close(string $id): JsonResponse
    {
        $exception = $this->exceptionRepo->find($id);

        if ($exception === null) {
            return $this->json(['error' => 'Exception not found'], Response::HTTP_NOT_FOUND);
        }

        if ($exception->isClosed()) {
            return $this->json(['error' => 'Exception already closed'], Response::HTTP_BAD_REQUEST);
        }

        $exception->close();
        $this->entityManager->flush();

        return $this->json([
            'data' => $this->formatException($exception),
            'message' => 'Exception closed successfully',
        ]);
    }

    /**
     * 获取异常统计.
     */
    #[Route('/stats/summary', name: 'order_exception_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $statusCounts = $this->exceptionRepo->countByStatus();
        $typeCounts = $this->exceptionRepo->countByType();

        return $this->json([
            'data' => [
                'byStatus' => $statusCounts,
                'byType' => $typeCounts,
            ],
        ]);
    }

    /**
     * 获取异常类型选项.
     */
    #[Route('/options/types', name: 'order_exception_type_options', methods: ['GET'])]
    public function typeOptions(): JsonResponse
    {
        return $this->json([
            'data' => OrderException::getTypeOptions(),
        ]);
    }

    /**
     * 获取处理方式选项.
     */
    #[Route('/options/resolutions', name: 'order_exception_resolution_options', methods: ['GET'])]
    public function resolutionOptions(): JsonResponse
    {
        return $this->json([
            'data' => OrderException::getResolutionOptions(),
        ]);
    }

    /**
     * 格式化异常数据.
     */
    private function formatException(OrderException $exception, bool $detailed = false): array
    {
        $order = $exception->getOrder();

        $data = [
            'id' => $exception->getId(),
            'exceptionNo' => $exception->getExceptionNo(),
            'type' => $exception->getType(),
            'typeLabel' => $exception->getTypeLabel(),
            'status' => $exception->getStatus(),
            'statusLabel' => $exception->getStatusLabel(),
            'description' => $exception->getDescription(),
            'resolution' => $exception->getResolution(),
            'resolutionLabel' => $exception->getResolutionLabel(),
            'resolutionNotes' => $exception->getResolutionNotes(),
            'resolvedBy' => $exception->getResolvedBy(),
            'resolvedAt' => $exception->getResolvedAt()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $exception->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $exception->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'order' => [
                'id' => $order->getId(),
                'externalOrderNo' => $order->getExternalOrderNo(),
                'status' => $order->getStatus(),
                'totalAmount' => $order->getTotalAmount(),
            ],
        ];

        if ($detailed) {
            $data['details'] = $exception->getDetails();
        }

        return $data;
    }
}
